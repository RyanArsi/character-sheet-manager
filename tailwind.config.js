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
        },
    },

    plugins: [forms],

    safelist: [
        'text-orange-400', 'text-green-400', 'text-red-400', 'text-red-600',
        'text-purple-400', 'text-cyan-400', 'text-pink-400',
        'text-blue-400',
        // training level badges
        'border-green-500', 'bg-green-900/40', 'text-green-400',
        'border-blue-500', 'bg-blue-900/40', 'text-blue-400',
        'border-yellow-400', 'bg-yellow-900/40', 'text-yellow-300',
        'border-orange-400', 'bg-orange-900/40', 'text-orange-300',
        'border-red-500', 'bg-red-900/40', 'text-red-400',
        'animate-pulse',
    ],
};
