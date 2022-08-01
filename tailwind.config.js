/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./resources/views/**/*.blade.php'],
  theme: {
    extend: {
      colors: {
        primary: 'var(--primary)',
        secondary: 'var(--secondary)',
        info: 'var(--info)',
        warning: 'var(--warning)',
        success: 'var(--success)',
        danger: 'var(--danger)',
        sidebar: 'var(--sidebar)',
        documentation: 'var(--documentation)',
        navbar: 'var(--navbar)',
      }
    },
  },
  plugins: [],
}
