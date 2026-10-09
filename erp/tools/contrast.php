<?php

/*
 * Contrast checker for the design tokens.
 *
 * The palette in docs/05-UIUX.md is described as validated, and this is what
 * validates it: every colour the interface puts text in, measured against the
 * surface it actually sits on, in both themes.
 *
 * Run it after touching a token:
 *
 *     php tools/contrast.php
 *
 * WCAG 2.1: 4.5:1 for body text, 3:1 for large text (>=18.66px bold or 24px)
 * and for the non-text parts of a control. A figure on a dashboard is body
 * text, so 4.5 is the bar unless the row says otherwise.
 */
function luminance(string $hex): float
{
    $hex = ltrim($hex, '#');

    $channel = function (int $value): float {
        $v = $value / 255;

        return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * $channel((int) hexdec(substr($hex, 0, 2)))
        + 0.7152 * $channel((int) hexdec(substr($hex, 2, 2)))
        + 0.0722 * $channel((int) hexdec(substr($hex, 4, 2)));
}

function contrast(string $a, string $b): float
{
    $one = luminance($a);
    $two = luminance($b);

    return round((max($one, $two) + 0.05) / (min($one, $two) + 0.05), 2);
}

/**
 * The body of the first rule whose selector list names this selector.
 *
 * Accepts a list as well as a lone selector, because the palette is now one
 * block — `:root, .dark` — rather than a light block and a dark one.
 */
function block(string $css, string $selector): ?string
{
    return preg_match('/(?<=[\s,])'.preg_quote($selector, '/').'(?:\s*,[^{]*)?\s*\{(.*?)\n    \}/s', $css, $found)
        ? $found[1]
        : null;
}

/** A solid hex token from a block body; null if absent or not solid. */
function value(?string $body, string $name): ?string
{
    if ($body === null) {
        return null;
    }

    return preg_match('/'.preg_quote($name, '/').':\s*(#[0-9a-fA-F]{6})\b/', $body, $found)
        ? $found[1]
        : null;
}

$css = file_get_contents(__DIR__.'/../resources/css/filament/admin/theme.css');

/**
 * Every token, and the bar it has to clear.
 *
 * 4.5 for anything that carries words or figures; 3.0 for anything that is a
 * shape — a bar segment, a chart line, a status dot. That split is why the
 * status colours come in pairs: no single yellow is both readable as text on
 * white and vivid enough to be worth using as a fill.
 */
$foregrounds = [
    '--erp-text-primary' => 4.5,
    '--erp-text-secondary' => 4.5,
    '--erp-text-muted' => 4.5,

    '--erp-good' => 3.0,
    '--erp-good-text' => 4.5,
    '--erp-warning' => 3.0,
    '--erp-warning-text' => 4.5,
    '--erp-serious' => 3.0,
    '--erp-serious-text' => 4.5,
    '--erp-critical' => 3.0,
    '--erp-critical-text' => 4.5,

    '--erp-axis-text' => 3.0,   // chart furniture, not prose
    '--erp-series-1' => 3.0,
    '--erp-series-2' => 3.0,
    '--erp-series-3' => 3.0,
    '--erp-series-4' => 3.0,
    '--erp-series-5' => 3.0,
    '--erp-series-6' => 3.0,
    '--erp-series-7' => 3.0,
    '--erp-series-8' => 3.0,
];

$failures = 0;

$root = block($css, ':root');
$dark = block($css, '.dark');

/*
 * One theme or two. The panel runs dark-only, its palette a single
 * `:root, .dark` block; measuring that block twice under two names would only
 * print the same table twice and call half of it "light".
 */
$themes = $root !== null && $root === $dark
    ? ['dark (the only theme)' => $dark]
    : array_filter(['light' => $root, 'dark' => $dark]);

foreach ($themes as $theme => $body) {
    /*
     * What the text actually sits on. On a glass design that is the glass as
     * it reads over the field — --erp-bg-solid, the flattened colour — since
     * --erp-bg-surface is translucent and has no single colour to measure.
     */
    $surface = value($body, '--erp-bg-solid')
        ?? value($body, '--erp-bg-surface')
        ?? value($root, '--erp-bg-surface');

    echo "\n".strtoupper($theme).'  — on surface '.($surface ?? '(none)')."\n";
    echo str_repeat('─', 58)."\n";

    if ($surface === null) {
        echo "No solid surface colour to measure against.\n";
        $failures++;

        continue;
    }

    foreach ($foregrounds as $name => $minimum) {
        // A token the dark block does not restate is inherited from :root,
        // which is exactly the mistake worth catching.
        $inherited = value($body, $name) === null;
        $colour = value($body, $name) ?? value($root, $name);

        if ($colour === null) {
            continue;
        }

        $ratio = contrast($colour, $surface);
        $ok = $ratio >= $minimum;

        if (! $ok) {
            $failures++;
        }

        printf(
            "%-22s %-8s %5.2f:1  need %.1f  %s%s\n",
            $name,
            $colour,
            $ratio,
            $minimum,
            $ok ? 'ok' : 'FAILS',
            $inherited && $theme === 'dark' ? '   (inherited from :root)' : '',
        );
    }
}

echo "\n".($failures === 0
    ? "Every token clears its threshold.\n"
    : "{$failures} token(s) below threshold.\n");

exit($failures === 0 ? 0 : 1);
