/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./assets/**/*.js",
    "./templates/**/*.html.twig",
  ],
  theme: {
    extend: {
      colors: {
        // Brand accent. Registering it here (instead of hand-written
        // .bg-pinegreen rules) makes every variant work: border-pinegreen,
        // focus:ring-pinegreen, bg-pinegreen/20, …
        pinegreen: {
          DEFAULT: '#157A6E',
          40: '#0C4941',
        },
      },
    },
  },
  plugins: [],
}
