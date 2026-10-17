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
    // budget_kb: a client index over this size (gzipped) is reported, so a set
    // that outgrows the browser index can move to the server driver.
    'search' => [
        'driver' => 'client',
        'budget_kb' => 300,
    ],

    // Snippet includes (<<< @/app/Billing/Invoice.php#refund) resolve inside this
    // folder. null means the app's base path for repository sets. Database sets
    // include nothing unless a set sets its own sets.{key}.snippets.root.
    // Dotfiles (.env), keys, storage/ and auth.json are never included.
    'snippets' => [
        'root' => null,
    ],

    'migrations' => [
        'path' => database_path('migrations/docs'),
        'skip_in_tests' => false,
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

    // Live references (<Route>, <Artisan>, <Model>, <Event>) read the app's public
    // shape only. <Model> describes classes in these namespaces and nothing else;
    // <Event> looks up short names in these namespaces.
    'references' => [
        'models' => [
            'allow' => ['App\\Models\\*'],
        ],
        'events' => [
            'namespaces' => ['App\\Events'],
        ],
    ],

    'ui' => [
        // Set LARECIPE_UI_DEV=http://localhost:5174 to load the UI from the Vite dev server.
        'dev_server' => env('LARECIPE_UI_DEV'),
    ],
];
