import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Posts/PostState.php', // the badge colours
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Inter Tight"', ...defaultTheme.fontFamily.sans],
            },
            // inglelandi.com: orange #FF4D1D / #E12D00 on #212121, light surfaces #F5F5F5
            colors: {
                brand: { 50: '#FFF3EE', 100: '#FFE4DB', 200: '#FFC7B5', 300: '#FFA088', 400: '#FF7A55', 500: '#FF4D1D', 600: '#E12D00', 700: '#B82500', 800: '#8F1D00', 900: '#661500' },
                ink: '#212121',
                paper: '#F5F5F5',
            },
        },
    },

    plugins: [forms],
};
