/**
 * Tailwind CSS configuration for Constructour (2025)
 *
 * Этот файл определяет базовую конфигурацию Tailwind, включая
 * дизайн‑токены для цветов, шрифтов, отступов и радиусов. Здесь
 * определены две темы (светлая и тёмная) через CSS‑переменные.
 *
 * Используйте этот файл как единый источник правды для всех
 * визуальных параметров. Не задавайте хардкодные цвета и размеры
 * напрямую в коде компонентов. Вместо этого обращайтесь к
 * классу‑утилите (например, `bg-primary`, `text-secondary` или
 * `rounded-lg`).
 */

const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
  content: [
    './src/**/*.{js,jsx,ts,tsx}',
    './pages/**/*.{js,jsx,ts,tsx}',
    './components/**/*.{js,jsx,ts,tsx}',
    './app/**/*.{js,jsx,ts,tsx}',
    // MD/MDX files can also be scanned if you document components
    './docs/**/*.{md,mdx}',
  ],
  darkMode: 'class',
  theme: {
    container: {
      center: true,
      padding: '1rem',
    },
    extend: {
      /**
       * Цвета определены в формате OKLCH. Каждое значение
       * использует CSS‑переменные, чтобы обеспечить лёгкую
       * смену тем. <alpha-value> автоматически заменяется
       * Tailwind на необходимое значение прозрачности.
       */
      colors: {
        primary: {
          DEFAULT: 'oklch(var(--primary) / <alpha-value>)',
          foreground: 'oklch(var(--on-primary) / <alpha-value>)',
        },
        secondary: {
          DEFAULT: 'oklch(var(--secondary) / <alpha-value>)',
          foreground: 'oklch(var(--on-secondary) / <alpha-value>)',
        },
        accent: {
          DEFAULT: 'oklch(var(--accent) / <alpha-value>)',
          foreground: 'oklch(var(--on-accent) / <alpha-value>)',
        },
        background: 'oklch(var(--background) / <alpha-value>)',
        surface: 'oklch(var(--surface) / <alpha-value>)',
        border: 'oklch(var(--border) / <alpha-value>)',
        muted: 'oklch(var(--muted) / <alpha-value>)',
        success: 'oklch(var(--success) / <alpha-value>)',
        warning: 'oklch(var(--warning) / <alpha-value>)',
        error: 'oklch(var(--error) / <alpha-value>)',
        info: 'oklch(var(--info) / <alpha-value>)',
      },
      /**
       * Типографика: основной шрифт Roboto. Если вы
       * используете другие шрифты (например, японский Noto Sans
       * JP), добавьте их здесь. tailwindcss автоматически
       * подключает fallbacks system-ui.
       */
      fontFamily: {
        sans: ['Roboto', ...defaultTheme.fontFamily.sans],
      },
      /**
       * Скругление углов. Эти значения используются для карточек,
       * модальных окон и других элементов. Изменяйте их централизованно.
       */
      borderRadius: {
        sm: '8px',
        md: '12px',
        lg: '16px',
        xl: '20px',
        '2xl': '24px',
      },
      /**
       * Тени. Для эффекта «жидкого стекла» задаём мягкие тени.
       */
      boxShadow: {
        glass: '0 4px 12px rgba(0, 0, 0, 0.08)',
        card: '0 8px 24px rgba(0, 0, 0, 0.1)',
      },
      /**
       * Отступы. Следуем 8 px grid, поэтому переопределяем
       * несколько значений для кратных шагов.
       */
      spacing: {
        1: '4px',
        2: '8px',
        3: '12px',
        4: '16px',
        5: '20px',
        6: '24px',
        8: '32px',
        10: '40px',
        12: '48px',
        16: '64px',
      },
      /**
       * Задние фильтры. Используются для Liquid Glass —
       * размытия заднего фона. Значения можно расширять.
       */
      backdropBlur: {
        sm: '4px',
        DEFAULT: '8px',
        md: '12px',
        lg: '16px',
      },
    },
  },
  plugins: [
    require('@tailwindcss/forms'), // стилизация форм
    require('@tailwindcss/typography'), // базовая типографика
    require('@tailwindcss/aspect-ratio'), // для соотношения сторон изображений
  ],
};