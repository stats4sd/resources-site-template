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
                'brand-primary': 'var(--brand-primary)',
                'brand-secondary': 'var(--brand-secondary)',
                'brand-bg': 'var(--brand-bg)',
                'brand-footer-bg': 'var(--brand-footer-bg)',
                'brand-footer-text': 'var(--brand-footer-text)',
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
