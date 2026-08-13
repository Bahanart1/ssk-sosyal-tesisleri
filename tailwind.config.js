import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/**
 * Renk sistemi iki katmanlıdır:
 *
 *  1. Sabit ölçekler (accent, navy) — kimlik renkleri, her iki modda aynı hex.
 *  2. Semantik tokenlar (canvas, surface, line, ink…) — CSS değişkenine bağlıdır;
 *     karanlık mod tek yerden, resources/css/app.css içinde çevrilir.
 *
 * Arayüzde mümkün olduğunca semantik tokenlar kullanılır; böylece her sınıfa
 * ayrı bir dark: karşılığı yazmak gerekmez.
 */
const token = (name) => `rgb(var(--c-${name}) / <alpha-value>)`;

export default {
    darkMode: 'class',
    content: [
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Inter"', '"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Inter"', '"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // --- Sabit kimlik ölçekleri ---
                accent: {
                    50: '#eef4fb',
                    100: '#d9e6f5',
                    200: '#b6cfeb',
                    300: '#86b0dc',
                    400: '#4f8bc9',
                    500: '#2f6cb0',
                    600: '#1d4f91',
                    700: '#184176',
                    800: '#16355e',
                    900: '#142c4d',
                    950: '#0d1c33',
                },
                navy: {
                    50: '#f1f5f9',
                    100: '#e2e8f0',
                    200: '#cbd5e1',
                    300: '#94a3b8',
                    400: '#64748b',
                    500: '#475569',
                    600: '#334155',
                    700: '#1e293b',
                    800: '#152232',
                    900: '#0f1b28',
                    950: '#0a121b',
                },

                // --- Semantik tokenlar (moda göre çevrilir) ---
                canvas: token('canvas'),
                surface: {
                    DEFAULT: token('surface'),
                    alt: token('surface-alt'),
                    sunken: token('surface-sunken'),
                },
                line: {
                    DEFAULT: token('line'),
                    soft: token('line-soft'),
                },
                ink: {
                    DEFAULT: token('ink'),
                    muted: token('ink-muted'),
                    subtle: token('ink-subtle'),
                },
                chrome: {
                    DEFAULT: token('chrome'),
                    ink: token('chrome-ink'),
                    muted: token('chrome-muted'),
                },
            },
            boxShadow: {
                soft: '0 1px 2px rgb(15 27 40 / 0.04), 0 2px 8px rgb(15 27 40 / 0.05)',
                lift: '0 2px 4px rgb(15 27 40 / 0.05), 0 8px 24px rgb(15 27 40 / 0.10)',
                inset: 'inset 0 1px 0 rgb(255 255 255 / 0.04)',
            },
            borderRadius: {
                xl2: '0.875rem',
            },
            animation: {
                'fade-in': 'fadeIn .3s ease-out both',
                'slide-up': 'slideUp .3s cubic-bezier(.22,1,.36,1) both',
                'rise': 'rise .35s cubic-bezier(.22,1,.36,1) both',
            },
            keyframes: {
                fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
                slideUp: {
                    '0%': { opacity: 0, transform: 'translateY(6px)' },
                    '100%': { opacity: 1, transform: 'translateY(0)' },
                },
                rise: {
                    '0%': { opacity: 0, transform: 'translateY(10px)' },
                    '100%': { opacity: 1, transform: 'translateY(0)' },
                },
            },
        },
    },
    plugins: [forms],
};
