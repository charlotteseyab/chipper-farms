<?php

namespace Tests\Feature\Views;

use Tests\TestCase;

/**
 * Phase 17 (§55, §60) — classes chosen at runtime must actually exist.
 *
 * WHAT THIS CATCHES
 * -----------------
 * A Blade template that builds a class name by interpolation —
 * `bg-{{ $row['weighingStatus']['color'] }}-100` — is invisible to Tailwind's
 * scanner, which reads source files as text and looks for complete class names.
 * Nothing is generated, and the element renders correctly classed and entirely
 * unstyled.
 *
 * This is a nasty defect to catch any other way. It is not a layout problem, so
 * a viewport review may not notice it; the markup assertion passes because the
 * class attribute is exactly what the template says; and nothing errors. The
 * colour is just quietly absent. Phase 8 hit this once already and fixed the one
 * occurrence it had introduced — a Phase 17 sweep found five more files still
 * doing it, on three dashboards a farm manager opens every day.
 *
 * THE RULE
 * --------
 * Interpolation is allowed. What is not allowed is interpolating a
 * utility/shade pair the safelist in `tailwind.config.js` does not cover,
 * because that is the pair that will not be generated.
 *
 * This test reads the config rather than duplicating the list, so the two
 * cannot drift apart.
 */
class ResponsiveClassSafetyTest extends TestCase
{
    /**
     * Utilities whose value comes from a colour name.
     *
     * `from-` and `to-` (gradients) are included in the search because they
     * take colours too; if a view starts interpolating one, this test says so
     * rather than letting it render flat.
     */
    private const COLOUR_UTILITIES = ['bg', 'text', 'border', 'from', 'to'];

    /**
     * @return list<array{file:string, line:int, utility:string, shade:string, snippet:string}>
     */
    protected function interpolatedColourClasses(): array
    {
        $found = [];
        $utilities = implode('|', self::COLOUR_UTILITIES);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $lines = file($file->getPathname());

            foreach ($lines as $index => $line) {
                if (! preg_match_all(
                    '/(' . $utilities . ')-\{\{.*?\}\}-(\d{2,3})/',
                    $line,
                    $matches,
                    PREG_SET_ORDER
                )) {
                    continue;
                }

                foreach ($matches as $match) {
                    $found[] = [
                        'file'    => str_replace(base_path() . '/', '', $file->getPathname()),
                        'line'    => $index + 1,
                        'utility' => $match[1],
                        'shade'   => $match[2],
                        'snippet' => trim($match[0]),
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * @return array{utilities:list<string>, shades:list<string>}
     */
    protected function safelistedPairs(): array
    {
        $config = file_get_contents(base_path('tailwind.config.js'));

        $this->assertMatchesRegularExpression(
            '/safelist:\s*\[/',
            $config,
            'tailwind.config.js has no safelist, so every runtime-chosen colour class is missing.'
        );

        preg_match('/pattern:\s*\/\((?<utilities>[^)]+)\)-\((?<colours>[^)]+)\)-\((?<shades>[^)]+)\)/', $config, $m);

        $this->assertNotEmpty($m, 'Could not read the safelist pattern out of tailwind.config.js.');

        return [
            'utilities' => explode('|', $m['utilities']),
            'shades'    => explode('|', $m['shades']),
        ];
    }

    public function test_every_runtime_chosen_colour_class_is_safelisted(): void
    {
        $safelist = $this->safelistedPairs();
        $unsafe   = [];

        foreach ($this->interpolatedColourClasses() as $usage) {
            $utilityCovered = in_array($usage['utility'], $safelist['utilities'], true);
            $shadeCovered   = in_array($usage['shade'], $safelist['shades'], true);

            if (! $utilityCovered || ! $shadeCovered) {
                $unsafe[] = "{$usage['file']}:{$usage['line']} — {$usage['snippet']}";
            }
        }

        $this->assertSame(
            [],
            $unsafe,
            "These templates build a class name at runtime that Tailwind will never generate, so the "
            . "element renders unstyled with no error anywhere:\n  " . implode("\n  ", $unsafe)
            . "\n\nEither add the utility/shade to the safelist in tailwind.config.js, or emit a "
            . 'complete class string from a match() instead of interpolating a fragment.'
        );
    }

    /**
     * The sweep must actually be looking at something.
     *
     * Without this, deleting the views directory — or a regex that matches
     * nothing — would leave the test above green and meaningless.
     */
    public function test_the_sweep_reads_a_real_body_of_templates(): void
    {
        $count = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'))
        );

        foreach ($files as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $count++;
            }
        }

        $this->assertGreaterThan(
            100,
            $count,
            "Only {$count} Blade templates were scanned, which is too few to be the real view tree."
        );
    }

    /**
     * The known occurrences, pinned.
     *
     * Not a requirement that they exist — it is a record of where the pattern
     * lives, so that if someone later rewrites those dashboards to emit complete
     * strings and drops the safelist, the change is deliberate rather than a
     * silent regression waiting for the next interpolation.
     */
    public function test_the_known_interpolating_templates_are_still_covered(): void
    {
        $files = array_unique(array_column($this->interpolatedColourClasses(), 'file'));

        $this->assertNotEmpty(
            $files,
            'No template interpolates a colour class any more. If those dashboards were rewritten to '
            . 'emit complete class strings, this test and the tailwind.config.js safelist can both go.'
        );
    }
}
