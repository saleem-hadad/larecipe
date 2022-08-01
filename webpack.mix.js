let mix = require('laravel-mix');

mix.postCss("resources/css/app.css", "publishable/assets/css", [
		require("tailwindcss")
	])
	.js('resources/js/app.js', 'publishable/assets/js').vue()
	.copy('publishable/assets', '../dev/public/vendor/binarytorch/larecipe/assets');