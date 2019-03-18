const { mix } = require('laravel-mix');

var tailwindcss = require('tailwindcss');

mix.options({
		processCssUrls: false,
		postCss: [ tailwindcss('./tailwind.js') ],
	})
	.sass('resources/sass/light.scss', 'publishable/assets/css')
	.sass('resources/sass/dark.scss', 'publishable/assets/css')
	.js('resources/js/app.js', 'publishable/assets/js')
	.copy('publishable/assets', '../dev/public/vendor/binarytorch/larecipe/assets');