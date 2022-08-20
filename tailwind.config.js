/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ['./resources/views/**/*.blade.php', './resources/js/**/*.vue'],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: 'var(--primary)',
        secondary: 'var(--secondary)',
        info: 'var(--info)',
        warning: 'var(--warning)',
        success: 'var(--success)',
        danger: 'var(--danger)',
        'sidebar-light': 'var(--sidebar-light)',
        'documentation-light': 'var(--documentation-light)',
        'navbar-light': 'var(--navbar-light)',
        'sidebar-dark': 'var(--sidebar-dark)',
        'documentation-dark': 'var(--documentation-dark)',
        'navbar-dark': 'var(--navbar-dark)',
      }
    },
  },
  plugins: [],
}
