import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans:  ['DM Sans', ...defaultTheme.fontFamily.sans],
                mono:  ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                brand: {
                    DEFAULT: 'var(--color-brand)',
                    dark:    'var(--color-brand-dark)',
                    light:   'var(--color-brand-light)',
                },
                sidebar: {
                    bg:     'var(--sidebar-bg)',
                    border: 'var(--sidebar-border)',
                    hover:  'var(--sidebar-item-hover)',
                    active: 'var(--sidebar-item-active)',
                    text:   'var(--sidebar-text)',
                    'text-active': 'var(--sidebar-text-active)',
                },
                content: {
                    bg: 'var(--content-bg)',
                },
                surface: {
                    DEFAULT: 'var(--surface)',
                    border:  'var(--surface-border)',
                    hover:   'var(--surface-hover)',
                },
                'app-text': {
                    primary:   'var(--text-primary)',
                    secondary: 'var(--text-secondary)',
                    muted:     'var(--text-muted)',
                },
                success:  {
                    DEFAULT: 'var(--color-success)',
                    bg:      'var(--color-success-bg)',
                },
                danger:   {
                    DEFAULT: 'var(--color-danger)',
                    bg:      'var(--color-danger-bg)',
                },
                warning:  {
                    DEFAULT: 'var(--color-warning)',
                    bg:      'var(--color-warning-bg)',
                },
                info:     {
                    DEFAULT: 'var(--color-info)',
                    bg:      'var(--color-info-bg)',
                },
            },
            borderRadius: {
                sm: 'var(--radius-sm)',
                md: 'var(--radius-md)',
                lg: 'var(--radius-lg)',
            },
            boxShadow: {
                sm: 'var(--shadow-sm)',
                md: 'var(--shadow-md)',
                lg: 'var(--shadow-lg)',
            },
        },
    },

    plugins: [forms],
};
