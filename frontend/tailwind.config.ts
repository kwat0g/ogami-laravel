import type { Config } from 'tailwindcss'

export default {
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        // Minimalist palette - neutral with subtle accents
        primary: {
          DEFAULT: '#18181b', // zinc-900
          50:  '#fafafa',
          100: '#f4f4f5',
          500: '#71717a',
          600: '#52525b',
          700: '#3f3f46',
          900: '#18181b',
        },
        accent: {
          DEFAULT: '#2563eb', // minimal blue for key actions only
          soft: '#eff6ff',
        },
        danger:  { DEFAULT: '#dc2626', soft: '#fef2f2' },
        warning: { DEFAULT: '#ea580c', soft: '#fff7ed' },
        success: { DEFAULT: '#16a34a', soft: '#f0fdf4' },
        neutral: {
          50:  '#fafafa',
          100: '#f5f5f5',
          200: '#e5e5e5',
          300: '#d4d4d4',
          400: '#a3a3a3',
          500: '#737373',
          600: '#525252',
          700: '#404040',
          800: '#262626',
          900: '#171717',
        },
      },
      fontFamily: {
        // Currency / numeric display — JetBrains Mono
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
        sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      },
      spacing: {
        '18': '4.5rem',
        '72': '18rem',
        '84': '21rem',
        '96': '24rem',
      },
      screens: {
        xs: '480px',
      },
    },
  },
  plugins: [],
} satisfies Config
