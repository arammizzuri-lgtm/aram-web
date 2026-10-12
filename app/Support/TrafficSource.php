<?php

namespace App\Support;

/**
 * Where a visit came from — Google, Instagram, WhatsApp… — read from what the
 * browser tells us: a utm_source on the link, the in-app browser's user agent
 * (Instagram and Facebook open links inside their apps, and say so), or the
 * referring page.
 *
 * Decided once, when the visit is recorded, so the history never shifts if
 * these rules are refined later.
 *
 * WhatsApp is the hard one: its apps open links without saying where they
 * came from, so most WhatsApp visits land in "Direct / unknown". A link
 * that carries ?utm_source=whatsapp is counted properly.
 */
class TrafficSource
{
    /** Navigation inside the site — not a source at all. */
    public const INTERNAL = 'internal';

    /** @return array<string, string> key => label, in display order */
    public static function labels(): array
    {
        return [
            'google' => 'Google',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'whatsapp' => 'WhatsApp',
            'linkedin' => 'LinkedIn',
            'tiktok' => 'TikTok',
            'telegram' => 'Telegram',
            'snapchat' => 'Snapchat',
            'search' => 'Other search engines',
            'other' => 'Other websites',
            'direct' => 'Direct / unknown',
        ];
    }

    public static function label(?string $key): string
    {
        return self::labels()[$key ?? 'direct'] ?? ucfirst((string) $key);
    }

    public static function classify(?string $referrer, ?string $userAgent = null, ?string $utmSource = null, ?string $ownHost = null): string
    {
        // 1. A link tagged with utm_source says so outright.
        if (filled($utmSource)) {
            $utm = strtolower(trim($utmSource));
            $alias = ['ig' => 'instagram', 'fb' => 'facebook', 'wa' => 'whatsapp', 'li' => 'linkedin', 'tg' => 'telegram'];
            $utm = $alias[$utm] ?? $utm;

            return isset(self::labels()[$utm]) && $utm !== 'direct' ? $utm : 'other';
        }

        // 2. In-app browsers name themselves in the user agent.
        $ua = (string) $userAgent;
        foreach ([
            'instagram' => '/Instagram/i',
            'facebook' => '/FBAN|FBAV|FB_IAB|FBIOS|FB4A/',
            'linkedin' => '/LinkedInApp/i',
            'tiktok' => '/musical_ly|BytedanceWebview|TikTok/i',
            'snapchat' => '/Snapchat/i',
            'telegram' => '/Telegram/i',
        ] as $source => $pattern) {
            if ($ua !== '' && preg_match($pattern, $ua)) {
                return $source;
            }
        }

        // 3. The referring page.
        $referrer = trim((string) $referrer);
        if ($referrer === '') {
            return 'direct';
        }
        if (str_starts_with($referrer, 'android-app://')) {
            // Android apps opening a link in a custom tab: android-app://com.whatsapp/
            $app = strtolower(substr($referrer, 14));

            return match (true) {
                str_starts_with($app, 'com.whatsapp') => 'whatsapp',
                str_starts_with($app, 'com.instagram') => 'instagram',
                str_starts_with($app, 'com.facebook') => 'facebook',
                str_starts_with($app, 'com.linkedin') => 'linkedin',
                str_starts_with($app, 'org.telegram') => 'telegram',
                str_starts_with($app, 'com.google') => 'google',
                default => 'other',
            };
        }

        $host = strtolower(preg_replace('/^www\./', '', (string) parse_url($referrer, PHP_URL_HOST)));
        if ($host === '') {
            return 'direct';
        }
        if ($ownHost && $host === strtolower(preg_replace('/^www\./', '', $ownHost))) {
            return self::INTERNAL;
        }

        $is = fn (string ...$domains) => collect($domains)->contains(
            fn ($d) => $host === $d || str_ends_with($host, '.'.$d)
        );

        return match (true) {
            (bool) preg_match('/(^|\.)google\.[a-z.]+$/', $host) => 'google',
            $is('instagram.com') => 'instagram',
            $is('facebook.com', 'fb.com', 'fb.me', 'messenger.com') => 'facebook',
            $is('whatsapp.com', 'wa.me') => 'whatsapp',
            $is('linkedin.com', 'lnkd.in') => 'linkedin',
            $is('tiktok.com') => 'tiktok',
            $is('t.me', 'telegram.org', 'telegram.me') => 'telegram',
            $is('snapchat.com') => 'snapchat',
            $is('bing.com', 'duckduckgo.com', 'yahoo.com', 'yandex.ru', 'yandex.com', 'ecosia.org', 'baidu.com', 'brave.com') => 'search',
            default => 'other',
        };
    }
}
