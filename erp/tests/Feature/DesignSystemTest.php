<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The design system, kept honest.
 *
 * A design system is not a document, it is whatever the code actually does —
 * and every rule in this one was broken somewhere before these tests existed:
 * status colours inheriting across themes, nine views hand-rolling the same
 * card, a navigation group nobody declared, fifteen tables with no empty state.
 * None of it was noticed by reading. It was noticed by measuring.
 *
 * So the rules are measured now, and a screen that breaks one fails here rather
 * than on the customer's account page.
 */
class DesignSystemTest extends TestCase
{
    /**
     * Every colour, against the surface it sits on, in both themes.
     *
     * The palette called itself validated for months and had never been
     * measured; the first run found nine failures, the worst of them the colour
     * the overdue figure is printed in. Run as a test so it can never quietly
     * drift again — `php tools/contrast.php` prints the whole table.
     */
    #[Test]
    public function every_colour_clears_its_contrast_threshold(): void
    {
        // PHP_BINARY, not "php": the interpreter running the suite is not
        // necessarily the one on PATH, and on this machine there is none.
        $result = Process::path(base_path())->run(PHP_BINARY.' tools/contrast.php');

        $this->assertTrue(
            $result->successful(),
            "A design token is below its contrast threshold:\n\n".$result->output(),
        );
    }

    /**
     * Every table says something when it is empty.
     *
     * "No records found" tells you the query returned nothing and not one thing
     * more. An empty table is very often the first thing a new user sees, and
     * it is the best chance the system has to say what belongs there and how it
     * arrives.
     */
    #[Test]
    public function every_table_says_what_belongs_in_it(): void
    {
        $missing = [];

        foreach ($this->tableFiles() as $file) {
            $source = file_get_contents($file);

            if (! str_contains($source, 'emptyStateHeading')) {
                $missing[] = str_replace(app_path('Filament/'), '', $file);
            }
        }

        $this->assertSame([], $missing, 'these tables fall back to "No records found"');
    }

    /**
     * Nobody rebuilds the card by hand.
     *
     * Nine views were each writing `rounded-xl border` with the colours inlined
     * — Filament's own section rule, restated nine times and free to drift nine
     * ways, which is why no two screens quite matched. There is one card now,
     * and this is what keeps there being one.
     */
    #[Test]
    public function no_view_hand_rolls_the_card(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $file) {
            // The card component may name the pattern it replaced.
            if (str_contains($file, 'components'.DIRECTORY_SEPARATOR.'erp')) {
                continue;
            }

            $source = file_get_contents($file);

            // Strip Blade comments before looking: a comment explaining the old
            // pattern is not the old pattern.
            $code = preg_replace('/\{\{--.*?--\}\}/s', '', $source);

            if (preg_match('/rounded-xl\s+border/', (string) $code)) {
                $offenders[] = str_replace(resource_path('views/'), '', $file);
            }
        }

        $this->assertSame([], $offenders, 'use <x-erp.card> or the .erp-card class');
    }

    /**
     * Nothing wrapping the page may carry a transform.
     *
     * Filament positions a modal with `fixed inset-0` and renders it inline in
     * the page rather than teleporting it to the body, so it depends on the
     * viewport being its containing block. An element with a *filling*
     * transform animation becomes the containing block for its fixed
     * descendants — even after the animation settles on `transform: none` —
     * and an eight-pixel entrance flourish on `.fi-page-content > *` therefore
     * squeezed every dialog in the system into the box of whatever it happened
     * to sit inside, with its buttons below the fold.
     *
     * `translate`, `scale`, `rotate`, `filter`, `perspective` and `will-change`
     * on that property all do the same thing. Nothing in the browser reports
     * any of it, which is why it is checked here.
     */
    #[Test]
    public function the_page_entrance_animation_cannot_reparent_a_modal(): void
    {
        $css = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $this->assertMatchesRegularExpression(
            '/\.fi-page-content\s*>\s*\*\s*\{[^}]*animation:/',
            $css,
            'the page entrance animation has moved — update this guard',
        );

        preg_match('/\.fi-page-content\s*>\s*\*\s*\{[^}]*animation:\s*([\w-]+)/', $css, $applied);

        $name = $applied[1] ?? null;

        $this->assertNotNull($name, 'could not read the animation name');

        preg_match('/@keyframes\s+'.preg_quote((string) $name, '/').'\s*\{(.*?)\n\}/s', $css, $frames);

        $this->assertNotEmpty($frames[1] ?? '', "@keyframes {$name} is missing");

        $this->assertDoesNotMatchRegularExpression(
            '/\b(transform|translate|scale|rotate|perspective)\s*:/',
            $frames[1],
            "@keyframes {$name} runs on a page-level container, so a transform in it "
            .'becomes the containing block for every fixed-position modal inside the page',
        );
    }

    /**
     * Nobody rebuilds the small-caps label by hand either.
     *
     * Six views were each writing the same four utilities and the same inline
     * colour above a figure. `.erp-label` is that, once — and once means it can
     * be changed once.
     */
    #[Test]
    public function no_view_hand_rolls_the_label(): void
    {
        $offenders = [];

        foreach ($this->viewFiles() as $file) {
            $code = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($file));

            if (preg_match('/text-xs\s+font-semibold\s+uppercase/', (string) $code)) {
                $offenders[] = str_replace(resource_path('views/'), '', $file);
            }
        }

        $this->assertSame([], $offenders, 'use the .erp-label class');
    }

    /**
     * Every custom property a view asks for actually exists.
     *
     * `var(--erp-status-warning)` sat in the AI assistant view for months. There
     * is no such token — the name is `--erp-warning` — so the border it drew was
     * no border at all, and nothing anywhere said so. CSS fails silently, which
     * is exactly why it needs a test.
     */
    #[Test]
    public function every_token_a_view_uses_is_defined(): void
    {
        $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

        $unknown = [];

        foreach ($this->viewFiles() as $file) {
            preg_match_all('/var\((--erp-[a-z0-9-]+)\)/i', file_get_contents($file), $used);

            foreach (array_unique($used[1] ?? []) as $token) {
                if (! str_contains($theme, $token.':')) {
                    $unknown[] = $token.'  in  '.str_replace(resource_path('views/'), '', $file);
                }
            }
        }

        $this->assertSame([], array_unique($unknown), 'these custom properties are never defined');
    }

    // ----------------------------------------------------------------- files

    /** @return array<int, string> */
    private function tableFiles(): array
    {
        return array_values(array_filter(
            $this->phpFilesIn(app_path('Filament')),
            function (string $file): bool {
                $source = file_get_contents($file);

                if (! preg_match('/public static function table\(|configure\(Table/', $source)) {
                    return false;
                }

                /*
                 * A resource that hands its table straight to another class has
                 * no table of its own to give an empty state to — the class it
                 * delegates to is in this list already.
                 */
                return ! preg_match('/return\s+\w+Table::configure\(/', $source);
            },
        ));
    }

    /** @return array<int, string> */
    private function viewFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    /** @return array<int, string> */
    private function phpFilesIn(string $directory): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
