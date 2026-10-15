<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * "Read aloud" for client quotes that have no voice note of their own.
 *
 * English is read by the visitor's own phone or computer (voices.js) — free,
 * and nothing is stored. Phones almost never carry a Kurdish voice, so a
 * Kurdish quote is read from a recording made once, when it is saved, by
 * kurdishtts.com (Sorani; the big speech services offer no Kurdish). The
 * page always says it is a computer voice: it must never pass for the
 * client's own.
 *
 * Settings live in site_settings, as the alert mailbox does — this host has
 * no shell and caches its configuration on deploy. The API key is stored
 * encrypted, under a prefix the public site is never given.
 */
class ReadAloud
{
    public const ENABLED = 'reader_enabled';

    public const API_KEY = 'reader_api_key';     // stored encrypted

    public const SPEAKER = 'reader_speaker';

    public const SPEED = 'reader_speed';

    /** The speaker group the voice is chosen from (kurdishtts.com's "v4": 453 Sorani voices). */
    public const MODEL = 'v4';

    public const ENDPOINT = 'https://www.kurdishtts.com/api/tts-proxy';

    public const SPEAKERS_ENDPOINT = 'https://www.kurdishtts.com/api/get-speakers';

    public const DEFAULT_SPEAKER = 'sorani_1';

    /** "Read aloud" is offered unless switched off in the admin. */
    public static function enabled(): bool
    {
        return setting(self::ENABLED, '1') !== '0';
    }

    /** Whether Kurdish recordings can be made: a key and a voice to make them with. */
    public static function kurdishReady(): bool
    {
        return self::enabled() && filled(self::apiKey());
    }

    public static function storeKey(string $key): void
    {
        SiteSetting::put(self::API_KEY, Crypt::encryptString(trim($key)), 'reader');
    }

    public static function hasKey(): bool
    {
        return filled(self::apiKey());
    }

    private static function apiKey(): ?string
    {
        $stored = (string) setting(self::API_KEY);
        if ($stored === '') {
            return null;
        }
        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;   // encrypted under another app key: as good as none
        }
    }

    public static function speaker(): string
    {
        return (string) (setting(self::SPEAKER) ?: self::DEFAULT_SPEAKER);
    }

    public static function speed(): float
    {
        $speed = (float) (setting(self::SPEED) ?: 1);

        return $speed >= 0.5 && $speed <= 2 ? $speed : 1.0;
    }

    /**
     * What a recording is made from: the words, then the voice and speed
     * that read them. A recording whose fingerprint still matches needs no
     * making again; one made from other words must not be played at all —
     * but one read in a voice since changed still says the right thing,
     * and is kept until it is made again.
     */
    public static function fingerprint(string $text): string
    {
        return self::wordsPart($text).sha1(implode('|', [self::MODEL, self::speaker(), self::speed()]));
    }

    /** The start of the fingerprint of every recording of these words. */
    public static function wordsPart(string $text): string
    {
        return sha1(trim($text)).':';
    }

    /**
     * The Sorani voices to choose from, as id => "Female 3", women first —
     * fetched from the service (no key needed) and kept for a day. Empty if
     * the service cannot be reached; the saved choice still stands.
     *
     * @return array<string, string>
     */
    public static function speakers(): array
    {
        $cacheKey = 'reader_speakers_'.self::MODEL;
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $list = [];
        try {
            $response = Http::timeout(6)->acceptJson()->get(self::SPEAKERS_ENDPOINT, ['model_version' => self::MODEL]);
            if ($response->successful()) {
                $list = collect($response->json('speakers', []))
                    ->filter(fn ($s) => ($s['dialect'] ?? '') === 'sorani' && filled($s['id'] ?? null))
                    ->sortBy(fn ($s) => [($s['gender'] ?? '') === 'female' ? 0 : 1, (int) preg_replace('/\D/', '', $s['name'] ?? '0')])
                    ->mapWithKeys(fn ($s) => [$s['id'] => ($s['name'] ?? $s['id'])])
                    ->all();
            }
        } catch (Throwable) {
            // unreachable: the saved voice stands, and the list is asked for again later
        }

        // a day when it came; ten minutes when it did not, so the admin page
        // does not wait on the service at every click
        Cache::put($cacheKey, $list, $list ? now()->addDay() : now()->addMinutes(10));

        return $list;
    }

    /**
     * Read $text aloud in Sorani: the recording, as MP3 (it plays on every
     * phone). Throws with a message fit to show in the admin.
     */
    public static function synthesize(string $text): string
    {
        $key = self::apiKey();
        if (! $key) {
            throw new RuntimeException('No kurdishtts.com API key is saved (Manage → Read aloud).');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['x-api-key' => $key])
                ->post(self::ENDPOINT, [
                    'text' => trim($text),
                    'speaker_id' => self::speaker(),
                    'dialect' => 'sorani',
                    'model_version' => self::MODEL,
                    'format' => 'mp3',
                    'speed' => self::speed(),
                ]);
        } catch (Throwable $e) {
            throw new RuntimeException('kurdishtts.com could not be reached: '.$e->getMessage());
        }

        if (! $response->successful()) {
            $why = match ($response->status()) {
                401 => 'the API key was not accepted — check it in Manage → Read aloud',
                403 => 'the plan does not allow it (characters used up, the plan has ended, or this voice is not in it)',
                400, 422 => 'the request was refused',
                default => 'the service answered with an error',
            };
            $detail = trim(strip_tags((string) ($response->json('error') ?? $response->json('message') ?? '')));

            throw new RuntimeException('kurdishtts.com: '.$why.' ('.$response->status().')'.($detail !== '' ? ' — '.$detail : ''));
        }

        $audio = $response->body();
        if (strlen($audio) < 256 || str_contains(strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new RuntimeException('kurdishtts.com did not send back a recording.');
        }

        return $audio;
    }
}
