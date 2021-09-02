<?php

return [
    'path' => env('LARECIPE_PATH', '/docs'),

    'source' => 'recourses/docs',

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

    'middleware' => [
        'web',
    ],

    'cache'        => [
        'enabled'  => false,
        'duration' => 5
    ],

    'branding'       => [
        'primary'    => '#787AF6',
        'secondary'  => '#2b9cf2',
    ],
];
