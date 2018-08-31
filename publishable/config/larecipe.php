<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Routes
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of the LaRecipe docs basic routes
    | where you can specify the url of your documentations, the location
    | of your docs and the landing page when a user visits /docs route.
    |
    |
    */

    'docs'        => [
        'route'   => '/docs',
        'path'    => '/resources/docs',
        'landing' => 'overview',
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Versions
    |--------------------------------------------------------------------------
    |
    | Here you may specify and set the versions and the default (latest) one
    | of your documentation's versions where you can redirect the user to
    | Just make sure that the default version is in the published list.
    |
    |
    */

    'versions'      => [
        'default'   => '1.0',
        'published' => [
            '1.0'
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Settings
    |--------------------------------------------------------------------------
    |
    | These options configure the additional behaviors of your documentation
    | where you can limit the access to only authenticated users in your
    | system and change the cache period, by default is set to 5 mins.
    |
    |
    */

    'settings' => [
        'auth' => false
    ],

    'cache'       => [
        'enabled' => true,
        'period'  => 5
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Repository
    |--------------------------------------------------------------------------
    |
    | This is an optional config you can set in order to show an external link
    | to your documentation's repository if you have one. Once you set the
    | value of the url, LaRecipe automatically will show the nav button.
    |
    |
    */

    'repository'   => [
        'provider' => 'github',
        'url'      => 'https://github.com/saleem-hadad/LaRecipe'
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    |
    | Here you can add configure the appearance of your docs. For example,
    | you can swap the default logo to custom one that matches your Id
    | Also, you can change the theme of your docs if you prefer that
    |
    | Currently Supported Themes: 'light'
    |
    */

    'ui'                 => [
        'logo'           => '/images/logo.svg',
        'fav'            => '/fav.png',
        'theme'          => 'light',
        'back_to_top'    => true,
        'additional_css' => [
            //'css/custom.css',
        ],
        'additional_js'  => [
            //'js/custom.js',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SEO
    |--------------------------------------------------------------------------
    |
    | These options configure the SEO settings of your docs. You can set the
    | author, the description and the keywords. Also, LaRecipe by default
    | sets the canonical link to the viewed page's link automatically.
    |
    */

    'seo'             => [
        'author'      => '',
        'description' => '',
        'keywords'    => ''
    ]
];
