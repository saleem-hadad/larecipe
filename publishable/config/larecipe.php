<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Documentation Routes
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of the Larecipe docs basic routes
    | where you can speicify the url of your documentations, the location
    | of your docs and the landing page when a user visits /docs route.
    |
    |
    */

    'docs'      => [
        'route'   => '/docs',
        'path'    => '/resources/docs',
        'landing' => 'overview'
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
    | where you can limit the access to only authinticated users in your
    | system, change the default theme and colors of the docs layout.
    |
    |
    */

    'settings' => [
        'auth' => false
    ]
];
