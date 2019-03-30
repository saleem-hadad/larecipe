let mix = require('laravel-mix')

mix.setPublicPath('dist')
    .js('resources/js/asset.js', 'js')
    .sass('resources/sass/asset.scss', 'css')
