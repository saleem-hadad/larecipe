<?php

return [
    'path' => env('LARECIPE_PATH', '/docs'),

    'source' => 'resources/docs',

    'landing' => 'overview',

    'languages'      => [
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

    'branding'       => [
        'favicon'    => '',
        'primary'    => '#787AF6',
        'secondary'  => '#2b9cf2',
    ],

    'middleware' => [
        'web',
    ],

    'cache'        => [
        'enabled'  => false,
        'duration' => 5
    ],
];
