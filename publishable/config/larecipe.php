<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Routes
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of the LaRecipe docs basic route
    | where you can specify the url of your documentations, the location
    | of your docs and the landing page when a user visits /docs route.
    |
    |
    */

    'docs'        => [
        'route'   => '/docs',
        'path'    => 'resources/docs',
        'landing' => 'overview',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Versions
    |--------------------------------------------------------------------------
    |
    | Here you may specify and set the versions and the default (latest) one
    | of your documentation's versions where you can redirect the user to.
    | Just make sure that the default version is in the published list.
    |
    |
    */

    'Languages'      => [
        'enabled'   => true,
        'default'   => 'en',
        'published' => [
            'en'
        ]
    ],

    'versions'      => [
        'enabled'   => true,
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
    | system. It is false initially so that guests can view your docs.
    | Middleware can be defined if auth is set to false. For example, if you want all users to be able to access your docs,
    | use web middleware. If you want just auth users, use auth middleware. Or, make your own middleware
    | to handle who can see your docs (don't forget to use gates for more granular control!).
    |
    |
    */

    'settings'       => [
        'auth'       => false,
        'ga_id'      => '',
        'middleware' => [
            'web',
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | Obviously rendering markdown at the back-end is costly especially if
    | the rendered files are massive. Thankfully, caching is considered
    | as a good option to speed up your app when having high traffic.
    |
    | Caching period unit: minutes
    |
    */

    'cache'       => [
        'enabled' => false,
        'period'  => 5
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | Here you can add configure the search functionality of your docs.
    | You can choose the default engine of your search from the list
    | However, you can also enable/disable the search's visibility
    |
    | Supported Search Engines: 'algolia', 'internal'
    |
    */

    'search'            => [
        'enabled'       => false,
        'default'       => 'algolia',
        'engines'       => [
            'internal'  => [
                'index' => ['h2', 'h3']
            ],
            'algolia'   => [
                'key'   => '',
                'index' => ''
            ]
        ]
    ],

    /*
    |--------------------------------------------------------------------------
    | Appearance
    |--------------------------------------------------------------------------
    |
    | Here you can add configure the appearance of your docs. For example,
    | you can set the primary and secondary colors that will give your
    | documentation a unique look. You can set the fav of your docs.
    |
    |
    */

    'ui'                 => [
        'code_theme'     => 'dark', // or: light
        'fav'            => '',     // eg: fav.png
        'colors'         => [
            'primary'    => '#787AF6',
            'secondary'  => '#2b9cf2'
        ],
        'theme_order'    => null // ['LaRecipeDarkTheme', 'customTheme']
    ],

    'blade-parser' => [
        'regex' => [
            'code-blocks' => [
                'match' => '/\<pre\>(.|\n)*?<\/pre\>/',
                'replacement' => '<code-block>',
            ]
        ]
    ]
];
