import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{jsx,tsx}',
    ],

    safelist: [
        // シフトパターンの表示色（DBから動的に設定されるため）
        'bg-teal-100', 'text-teal-800',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'system-ui', '-apple-system', 'sans-serif'],
            },
        },
    },

    plugins: [forms],
};
