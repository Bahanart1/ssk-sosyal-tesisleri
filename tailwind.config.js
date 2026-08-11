import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Source Serif 4"', 'Georgia', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                navy: {
                    50: '#eef2f7',
                    100: '#d6e0ec',
                    200: '#adc1d9',
                    300: '#7d9cc0',
                    400: '#4c72a0',
                    500: '#2f5480',
                    600: '#1f3d62',
                    700: '#162c49',
                    800: '#0f2038',
                    900: '#0a1728',
                    950: '#060f1b',
                },
                teal: {
                    50: '#fdf3ee',
                    100: '#fbe4d8',
                    200: '#f6c7ae',
                    300: '#f0a37c',
                    400: '#ea8155',
                    500: '#e8734a',
                    600: '#d35a32',
                    700: '#af4726',
                    800: '#8c3a22',
                    900: '#73331f',
                },
                sand: {
                    50: '#fdfaf5',
                    100: '#f7ede1',
                    200: '#efdcc7',
                    300: '#e2c4a3',
                },
            },
            boxShadow: {
                soft: '0 1px 2px rgba(90, 50, 20, 0.05), 0 8px 24px rgba(90, 50, 20, 0.07)',
                lift: '0 4px 8px rgba(90, 50, 20, 0.07), 0 16px 40px rgba(90, 50, 20, 0.12)',
                glow: '0 0 0 1px rgba(232, 115, 74, 0.20), 0 8px 28px rgba(232, 115, 74, 0.22)',
                inset: 'inset 0 1px 0 rgba(255,255,255,0.06)',
            },
            borderRadius: {
                xl2: '1.25rem',
            },
            backgroundImage: {
                'brand-mesh':
                    'radial-gradient(ellipse 80% 60% at 10% 20%, rgba(232,115,74,0.32), transparent 55%), radial-gradient(ellipse 70% 50% at 90% 10%, rgba(240,163,124,0.30), transparent 50%), radial-gradient(ellipse 60% 40% at 50% 100%, rgba(6,15,27,0.55), transparent 60%)',
                'app-wash':
                    'radial-gradient(ellipse 100% 80% at 50% -20%, rgba(232,115,74,0.10), transparent 55%), linear-gradient(180deg, #fdf8f2 0%, #fbede0 100%)',
                'noise':
                    "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E\")",
            },
            animation: {
                'fade-in': 'fadeIn .45s ease-out both',
                'slide-up': 'slideUp .45s cubic-bezier(.22,1,.36,1) both',
                'rise': 'rise .55s cubic-bezier(.22,1,.36,1) both',
            },
            keyframes: {
                fadeIn: { '0%': { opacity: 0 }, '100%': { opacity: 1 } },
                slideUp: {
                    '0%': { opacity: 0, transform: 'translateY(12px)' },
                    '100%': { opacity: 1, transform: 'translateY(0)' },
                },
                rise: {
                    '0%': { opacity: 0, transform: 'translateY(18px) scale(.98)' },
                    '100%': { opacity: 1, transform: 'translateY(0) scale(1)' },
                },
            },
        },
    },
    plugins: [forms],
};
