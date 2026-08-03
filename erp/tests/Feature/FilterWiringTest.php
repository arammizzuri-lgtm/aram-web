<?php

namespace Tests\Feature;

use Filament\Support\Concerns\EvaluatesClosures;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

/**
 * A filter that quietly filters nothing.
 *
 * Filament injects the query builder into these closures by **parameter name**.
 * Name it `$query` and you get the table's builder; name it `$q` and the
 * parameter falls through to the container, which happily constructs an Eloquent
 * builder with no model attached. Your `where` is then applied to a throwaway
 * object and discarded, so the toggle lights up and every row stays on screen.
 *
 * Four filters in this system were dead exactly that way — including "Still
 * unmatched" on payments and "Still unapproved" on purchases, both of which are
 * how you find the work that needs doing. Nothing reported it. The filter ran,
 * returned a builder, and the page rendered.
 *
 * The scope version is worse: a named scope on a model-less builder is a fatal
 * error rather than a no-op, so the customer invoices "Outstanding" filter was
 * a 500 waiting for someone to switch it on.
 *
 * Reading will not catch this — `$q` looks like `$query`. So it is measured.
 */
class FilterWiringTest extends TestCase
{
    /**
     * The names Filament will actually inject.
     *
     * From `InteractsWithTableQuery::apply()`, which passes exactly these.
     * Anything else reaches the container fallback in `EvaluatesClosures`.
     */
    private const INJECTED = ['query', 'data', 'state'];

    #[Test]
    public function every_filter_closure_names_its_builder_so_filament_injects_it(): void
    {
        $offenders = [];

        foreach ($this->filamentFiles() as $file) {
            $source = file_get_contents($file);

            /*
             * `->query(` and `->baseQuery(` on a filter, up to the first
             * parameter. A closure with no parameters is fine — it is not
             * asking for the builder at all.
             */
            preg_match_all(
                '/->(?:query|baseQuery|modifyQueryUsing|modifyBaseQueryUsing)\(\s*(?:static\s+)?(?:fn|function)\s*\(([^)]*)\)/',
                $source,
                $matches,
                PREG_OFFSET_CAPTURE,
            );

            foreach ($matches[1] as $index => [$parameters]) {
                if (trim($parameters) === '') {
                    continue;
                }

                // The first parameter is the one Filament is being asked for.
                $first = trim(explode(',', $parameters)[0]);

                if (! preg_match('/\$(\w+)$/', $first, $name)) {
                    continue;
                }

                if (in_array($name[1], self::INJECTED, true)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $matches[0][$index][1]), "\n") + 1;

                $offenders[] = sprintf(
                    '%s:%d  $%s — rename to $query',
                    str_replace(app_path('Filament').DIRECTORY_SEPARATOR, '', $file),
                    $line,
                    $name[1],
                );
            }
        }

        $this->assertSame([], $offenders, 'these closures never receive the table\'s query builder');
    }

    /**
     * The premise, checked rather than assumed.
     *
     * If a future Filament starts injecting by type as well as by name, the test
     * above becomes noise and should go. This fails first and says so.
     */
    #[Test]
    public function filament_still_resolves_these_closures_by_parameter_name_only(): void
    {
        $method = (new ReflectionClass(EvaluatesClosures::class))
            ->getMethod('resolveClosureDependencyForEvaluation');

        $source = implode('', array_slice(
            file($method->getFileName()),
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1,
        ));

        $this->assertStringContainsString(
            'array_key_exists($parameterName, $namedInjections)',
            $source,
            'Filament no longer resolves closure arguments by name — revisit FilterWiringTest',
        );

        $this->assertStringContainsString(
            'app()->make($typedParameterClassName)',
            $source,
            'the container fallback that makes a mis-named parameter silently useless has gone',
        );
    }

    /** @return array<int, string> */
    private function filamentFiles(): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Filament'))
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
