import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Colorblind-safe status scale — validated against protan/deutan/tritan
                // simulation, deliberately NOT a plain red→amber→green ramp (see
                // resources/views/components/urgency-badge.blade.php). Never rely on
                // these hues alone: always pair with an icon shape and a text label.
                status: {
                    good: '#0ca30c',
                    warning: '#fab219',
                    serious: '#ec835a',
                    critical: '#d03b3b',
                },
            },
        },
    },

    plugins: [forms],
};
