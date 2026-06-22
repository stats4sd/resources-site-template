import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import daisyui from 'daisyui';
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './vendor/chrisreedio/socialment/resources/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'stats4sd-red': 'var(--stats4sd-red)',
                'stats4sd-red-70': 'var(--stats4sd-red-transparent)',
                'stats4sd-grey': 'var(--stats4sd-grey)',
                'frn-green': 'var(--frn-green)',
                'ifa-darkgreen': 'var(--ifa-darkgreen)',
                'ifa-green': 'var(--ifa-green)',
                'ifa-yellow': 'var(--ifa-yellow)',
            }
        },
    },

    plugins: [
        forms,
        typography,
        daisyui
    ],

    daisyui: {
        themes: [],

      },
};
