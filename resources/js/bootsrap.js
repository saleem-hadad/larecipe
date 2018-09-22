window.jQuery = window.$ = require('jquery');

require('./vendor/prism.js');
// This enables all language support via CDN
Prism.plugins.autoloader.use_minified = true;
Prism.plugins.autoloader.languages_path = 'https://cdnjs.cloudflare.com/ajax/libs/prism/1.15.0/components/';

require('smoothscroll-for-websites');
