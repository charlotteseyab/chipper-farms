import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    /*
     * Colours chosen at runtime (Phase 17, blueprint §55).
     *
     * Several dashboards build class names by interpolation —
     * `bg-{{ $row['weighingStatus']['color'] }}-100`. Tailwind's scanner reads
     * the source as text and never sees a complete class name there, so none of
     * those utilities were generated and the status colours simply did not
     * render. Not a layout bug and not visible in any assertion about markup:
     * the element is present, correctly classed, and unstyled.
     *
     * Safelisting is the intended answer for class names the template cannot
     * spell out. It is preferred here over rewriting five dashboards to emit
     * complete strings, because the fix is mechanical, total, and does not
     * touch screens outside this programme's scope.
     *
     * The colour list is every value the controllers actually produce
     * (`'color' => '...'` across app/), and the utility/shade pairs are every
     * combination the views interpolate. `ResponsiveClassSafetyTest` fails if a
     * view starts interpolating a pair this list does not cover.
     */
    safelist: [
        {
            pattern: /(bg|text|border)-(blue|gray|green|orange|pink|purple|red|yellow)-(50|100|200|500|600|700|800)/,
        },
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Outfit', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
            },
        },
    },

    plugins: [forms, typography],
};
