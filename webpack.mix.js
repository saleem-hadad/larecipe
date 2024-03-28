let mix = require('laravel-mix');

mix.copy('node_modules/@larecipe/larecipe-ui/dist/app.js', 'publishable/assets/js')
	.copy('node_modules/@larecipe/larecipe-ui/dist/app.css', 'publishable/assets/css')
	.copy('publishable/assets', '../dev/public/vendor/saleem-hadad/larecipe/assets');