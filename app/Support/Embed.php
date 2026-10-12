<?php

namespace App\Support;

/**
 * Turns a film or 360° tour link, as pasted from the address bar of the site
 * that hosts it, into the address its player is embedded from.
 *
 * Only these four hosts are understood, on purpose: the result goes into an
 * <iframe> on the public site, so it must be a player we know, never an
 * arbitrary page. Anything else is refused (null) — the admin form says so.
 */
class Embed
{
    /**
     * @return array{provider: string, kind: 'video'|'tour', id: string, embed: string, thumb: ?string}|null
     */
    public static function parse(?string $url): ?array
    {
        $url = trim((string) $url);
        if ($url === '' || ! preg_match('#^https?://#i', $url)) {
            return null;
        }
        $parts = parse_url($url);
        $host = strtolower(preg_replace('/^(www\.|m\.)/', '', $parts['host'] ?? ''));
        $path = $parts['path'] ?? '';
        parse_str($parts['query'] ?? '', $query);

        // YouTube: watch?v=…, youtu.be/…, /shorts/…, /embed/…, /live/…
        if (in_array($host, ['youtube.com', 'youtu.be', 'youtube-nocookie.com'], true)) {
            $id = $host === 'youtu.be'
                ? trim($path, '/')
                : ($query['v'] ?? (preg_match('#^/(?:shorts|embed|live)/([^/]+)#', $path, $m) ? $m[1] : ''));
            if (! preg_match('/^[A-Za-z0-9_-]{11}$/', (string) $id)) {
                return null;
            }

            // the no-cookie host sets nothing until the visitor presses play
            return self::result('youtube', 'video', $id,
                'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1&rel=0&modestbranding=1',
                'https://i.ytimg.com/vi/'.$id.'/hqdefault.jpg');
        }

        // Vimeo: vimeo.com/123, vimeo.com/123/hash (unlisted), player.vimeo.com/video/123?h=hash
        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)
            && preg_match('#^/(?:video/)?(\d+)(?:/([0-9a-f]+))?#', $path, $m)) {
            $hash = $m[2] ?? ($query['h'] ?? null);

            return self::result('vimeo', 'video', $m[1],
                'https://player.vimeo.com/video/'.$m[1].'?'.http_build_query(array_filter([
                    'h' => $hash, 'autoplay' => 1, 'dnt' => 1, 'title' => 0, 'byline' => 0,
                ])));
        }

        // Matterport: my.matterport.com/show/?m=…
        if ($host === 'my.matterport.com' && preg_match('/^[A-Za-z0-9]{6,20}$/', (string) ($query['m'] ?? ''))) {
            return self::result('matterport', 'tour', $query['m'],
                'https://my.matterport.com/show/?m='.$query['m'].'&play=1&qs=1');
        }

        // Kuula: kuula.co/post/…, kuula.co/share/…, kuula.co/share/collection/…
        if ($host === 'kuula.co' && preg_match('#^/(post|share)/(collection/)?([A-Za-z0-9]+)#', $path, $m)) {
            $id = ($m[2] ?? '').$m[3];

            return self::result('kuula', 'tour', $id,
                'https://kuula.co/share/'.$id.'?logo=0&info=0&fs=1&vr=1&thumbs=1');
        }

        return null;
    }

    private static function result(string $provider, string $kind, string $id, string $embed, ?string $thumb = null): array
    {
        return compact('provider', 'kind', 'id', 'embed', 'thumb');
    }
}
