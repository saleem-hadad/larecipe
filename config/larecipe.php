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

    // Ask AI needs laravel/ai and a provider key. rate_limit is "requests,minutes"
    // per reader; daily_budget caps answers per set per day (null: no cap).
    'ai' => [
        'ask' => [
            'enabled' => env('LARECIPE_ASK', false),
            'provider' => env('LARECIPE_AI_PROVIDER'),
            'model' => env('LARECIPE_AI_MODEL'),
            'max_steps' => 6,
            'rate_limit' => '10,1',
            'daily_budget' => null,
            'max_messages' => 20,
            'max_question_length' => 2000,
        ],
        'sync' => [
            'provider' => env('LARECIPE_AI_PROVIDER'),
            'model' => env('LARECIPE_AI_MODEL'),
            'max_pages' => 20,
            'max_steps' => 30,
        ],
    ],

    // The MCP server. One web endpoint per set at /{prefix}/{path} (POST), plus
    // one local stdio server for every set (php artisan larecipe:mcp).
    // web.middleware authenticates private sets: ['auth:sanctum'] for Sanctum
    // tokens, or ['auth:api'] with Mcp::oauthRoutes() for laravel/mcp's OAuth.
    // Access is re-checked on every tool call either way.
    'mcp' => [
        'web' => [
            'enabled' => true,
            'path' => 'mcp',
            'author_tools' => false,
            'middleware' => [],
            'rate_limit' => '60,1',
        ],
        'local' => true,
    ],

    'agents' => [
        'markdown' => true,
        'llms_txt' => true,
        'llms_full' => true,
        'skill_md' => true,
        'well_known_skills' => true,
        'sitemap' => true,
        'ask_endpoint' => false,
        // llms.txt indexes stay under this many characters (the AFDocs
        // threshold); larger versions split into one file per group.
        'llms_txt_limit' => 50000,
        // llms-full.txt splits by group past this many characters.
        'llms_full_limit' => 400000,
        // larecipe:validate warns when a page's agent Markdown is larger.
        'markdown_budget' => 50000,
        // larecipe:validate warns when a client search index would be larger (gzip bytes).
        'search_budget' => 300000,
    ],

    // The "Was this page helpful?" widget and the submit_feedback MCP tool.
    // Both dispatch FeedbackSubmitted for the host to forward; nothing is stored.
    'feedback' => [
        'enabled' => true,
        'rate_limit' => '20,1',
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
