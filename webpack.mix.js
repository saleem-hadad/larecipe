const { mix } = require('laravel-mix');

var tailwindcss = require('tailwindcss');

mix.options({
		processCssUrls: false,
		postCss: [ tailwindcss('./tailwind.js') ],
	})
	.sass('resources/sass/app.scss', 'publishable/assets/css')
    .sass('resources/sass/font-awesome.scss', 'publishable/assets/css')
    .sass('resources/sass/font-awesome-v4-shims.scss', 'publishable/assets/css')
	.js('resources/js/app.js', 'publishable/assets/js')
	.copy('publishable/assets', '../dev/public/vendor/binarytorch/larecipe/assets');