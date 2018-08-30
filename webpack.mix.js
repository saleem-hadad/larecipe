const { mix } = require('laravel-mix');

mix.options({
	processCssUrls: false
}).sass('resources/sass/themes/light.scss', 'publishable/assets/css')
.js('resources/js/app.js', 'publishable/assets/js');