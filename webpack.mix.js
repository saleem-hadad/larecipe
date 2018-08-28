const { mix } = require('laravel-mix');

mix.options({
	processCssUrls: false
}).sass('resources/sass/app.scss', '../Qwork/public/vendor/binarytorch/larecipe/assets/css')
.js('resources/js/app.js', '../Qwork/public/vendor/binarytorch/larecipe/assets/js');