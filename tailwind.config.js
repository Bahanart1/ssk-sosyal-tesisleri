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
                    50: '#eefdfb',
                    100: '#d3f8f3',
                    200: '#a6f0e8',
                    300: '#6fe1d5',
                    400: '#3ec9bc',
                    500: '#1a9e92',
                    600: '#178b81',
                    700: '#166f68',
                    800: '#165954',
                    900: '#154a47',
                },
                sand: {
                    50: '#f7f5f1',
                    100: '#efebe3',
                    200: '#e2d9cb',
                    300: '#d0c2ad',
                },
            },
            boxShadow: {
                soft: '0 1px 2px rgba(10, 23, 40, 0.04), 0 8px 24px rgba(10, 23, 40, 0.06)',
                lift: '0 4px 8px rgba(10, 23, 40, 0.06), 0 16px 40px rgba(10, 23, 40, 0.10)',
                glow: '0 0 0 1px rgba(26, 158, 146, 0.18), 0 8px 28px rgba(26, 158, 146, 0.18)',
                inset: 'inset 0 1px 0 rgba(255,255,255,0.06)',
            },
            borderRadius: {
                xl2: '1.125rem',
            },
            backgroundImage: {
                'brand-mesh':
                    'radial-gradient(ellipse 80% 60% at 10% 20%, rgba(26,158,146,0.28), transparent 55%), radial-gradient(ellipse 70% 50% at 90% 10%, rgba(76,114,160,0.35), transparent 50%), radial-gradient(ellipse 60% 40% at 50% 100%, rgba(6,15,27,0.55), transparent 60%)',
                'app-wash':
                    'radial-gradient(ellipse 100% 80% at 50% -20%, rgba(26,158,146,0.08), transparent 55%), linear-gradient(180deg, #f3f1ec 0%, #eef2f6 100%)',
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
