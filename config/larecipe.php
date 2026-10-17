<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default doc set
    |--------------------------------------------------------------------------
    |
    | The set used by artisan commands and the local MCP server when none is
    | given. Most apps have exactly one set and never think about this.
    |
    */

    'default' => env('LARECIPE_DEFAULT_SET', 'docs'),

    /*
    |--------------------------------------------------------------------------
    | Doc sets
    |--------------------------------------------------------------------------
    |
    | Each set is an independent docs site inside this app: its own route, its
    | own source, its own larecipe.json and its own agent surfaces. Content and
    | presentation (name, theme, versions, navigation) live next to the docs in
    | larecipe.json and navigation.json, never here.
    |
    | route.prefix '' mounts a set at the domain root.
    | source.driver is 'repository' (recommended: docs in this repo) or
    | 'database' (managed in the admin UI, always behind a cache).
    | access.mode is 'public', 'auth' or 'gate' (the viewLarecipe gate).
    |
    */

    'sets' => [
        'docs' => [
            'route' => [
                'prefix' => env('LARECIPE_PREFIX', 'docs'),
                'domain' => null,
                'middleware' => ['web'],
            ],
            'source' => [
                'driver' => 'repository',
                'path' => base_path('docs'),
            ],
            'access' => [
                'mode' => 'public',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Defaults for every set (a set may override any of these)
    |--------------------------------------------------------------------------
    */

    // required: agent changes made outside a pull request are drafts until a
    // person publishes them. auto: they publish immediately (still revisioned).
    'review' => env('LARECIPE_REVIEW', 'required'),

    'cache' => [
        'store' => env('LARECIPE_CACHE_STORE'),
    ],

    // client (default): MiniSearch in the browser. server: the PHP ranker.
    // Any other value is a custom driver registered with LaRecipe::extendSearch().
    'search' => [
        'driver' => 'client',
    ],

    'migrations' => [
        'path' => database_path('migrations/docs'),
        'skip_in_tests' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database source models
    |--------------------------------------------------------------------------
    |
    | A set with source.driver 'database' may also set source.connection (a
    | database connection), source.cache (a persistent cache store; array and
    | null are refused outside tests) and source.disk (the filesystem disk for
    | assets). Point these keys at your own subclasses to add scopes, tenancy
    | or observers to the tables LaRecipe uses.
    |
    */

    'models' => [
        'page' => 'LaRecipe\\Domains\\Sources\\Models\\Page',
        'navigation' => 'LaRecipe\\Domains\\Sources\\Models\\Navigation',
        'manifest' => 'LaRecipe\\Domains\\Sources\\Models\\Manifest',
        'revision' => 'LaRecipe\\Domains\\Sources\\Models\\Revision',
        'asset' => 'LaRecipe\\Domains\\Sources\\Models\\Asset',
        'docs_migration' => 'LaRecipe\\Domains\\Sources\\Models\\DocsMigrationRecord',
    ],

    'ai' => [
        'ask' => [
            'enabled' => env('LARECIPE_ASK', false),
            'provider' => env('LARECIPE_AI_PROVIDER'),
            'model' => env('LARECIPE_AI_MODEL'),
            'max_steps' => 6,
            'rate_limit' => '10,1',
            'daily_budget' => null,
        ],
        'sync' => [
            'provider' => env('LARECIPE_AI_PROVIDER'),
            'model' => env('LARECIPE_AI_MODEL'),
            'max_pages' => 20,
            'max_steps' => 30,
        ],
    ],

    'mcp' => [
        'web' => [
            'enabled' => true,
            'path' => 'mcp',
            'author_tools' => false,
        ],
        'local' => true,
    ],

    'agents' => [
        'markdown' => true,
        'llms_txt' => true,
        'skill_md' => true,
        'well_known_skills' => true,
        'ask_endpoint' => false,
    ],

    'hooks' => [
        'push_commands' => [
            'git push',
            'gh pr create',
            'glab mr create',
            'hub pull-request',
        ],
    ],

    'references' => [
        'models' => [
            'allow' => ['App\\Models\\*'],
        ],
    ],

    'ui' => [
        // Set LARECIPE_UI_DEV=http://localhost:5174 to load the UI from the Vite dev server.
        'dev_server' => env('LARECIPE_UI_DEV'),
    ],
];
