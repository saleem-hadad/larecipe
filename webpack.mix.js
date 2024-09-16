const mix = require("laravel-mix");

var tailwindcss = require('tailwindcss');

mix.vue({
	version: 2,
	extractVueStyles: false,
	transformToRequire: {
		image: ["xlink:href", "href"]
	}
});

mix.options({
		resolve: {
			extensions: [
				"*",
				".js",
				".jsx",
				".json",
				".vue",
				".ts",
				".tsx",
				".sass",
				".scss",
				".css",
				".mp3",
				".woff2",
				".woff"
			]
		},
		processCssUrls: false,
		postCss: [ tailwindcss('./tailwind.js') ],
	})
	.sass('resources/sass/app.scss', 'publishable/assets/css')
    .sass('resources/sass/font-awesome.scss', 'publishable/assets/css')
    .sass('resources/sass/font-awesome-v4-shims.scss', 'publishable/assets/css')
	.js('resources/js/app.js', 'publishable/assets/js')
	.copy('publishable/assets', '../dev/public/vendor/binarytorch/larecipe/assets');
