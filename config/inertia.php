<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configure if and how Inertia uses Server Side Rendering
    | to pre-render your Vue / React application on the server.
    |
    */

    'ssr' => [
        'enabled' => false,
        'url' => 'http://127.0.0.1:13714',
    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used during Inertia testing.
    |
    */

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/Pages'),
        ],
    ],

];
