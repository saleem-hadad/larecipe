<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Doc Set
    |--------------------------------------------------------------------------
    |
    | This is the doc set that artisan commands and the local MCP server use
    | when no set is given. Most apps have just one set and never need to
    | think about this. The default works for nearly every application.
    |
    */

    'default' => env('LARECIPE_DEFAULT_SET', 'docs'),

    /*
    |--------------------------------------------------------------------------
    | Doc Sets
    |--------------------------------------------------------------------------
    |
    | Each set is an independent docs site inside this app, with its own route,
    | source, larecipe.json and agent surfaces. Content and presentation sit
    | next to the docs, in larecipe.json and navigation.json, never here.
    |
    | A route.prefix of '' mounts the set at the domain root. A source.driver
    | is 'repository' (recommended, the docs live in the repository) or it
    | is 'database' (managed in the admin UI and always behind a cache).
    |
    | The access.mode is 'public', 'auth' or 'gate' (the viewLarecipe gate).
    | A set can also override review, cache, search, ai, mcp, agents, the
    | references and migrations options, and the set's own value wins.
    |
    | The html_input option is 'escape' by default, and raw HTML in Markdown
    | shows as text. A repository set may 'allow' it, apart from the tags
    | GitHub filters, such as <script>. Database sets always escape it.
    |
    | The integrations.analytics key adds GA4, Plausible, Fathom, PostHog or
    | a custom https script to every page of the set, using the same keys
    | as larecipe.json does. A set's analytics config looks like this:
    |
    |   'integrations' => ['analytics' => [
    |       'plausible' => ['domain' => 'docs.example.com'],
    |       'posthog' => ['apiKey' => 'phc_...', 'host' => 'https://ph.example.com'],
    |   ]],
    |
    | These scripts run with every reader's session, so a database set reads
    | them from here only, as its larecipe.json is edited in the admin UI
    | and over MCP, where agents and editors can change it at any time.
    |
    | A repository set may also declare them in larecipe.json, where they are
    | reviewed with the code, and this file wins per provider. Self-hosted
    | Plausible, Fathom or PostHog hosts (src, host) are read only here.
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
    | Review Mode
    |--------------------------------------------------------------------------
    |
    | With 'required', changes that agents make outside a pull request stay
    | drafts until a person publishes them. With 'auto', they publish at
    | once, and every change keeps its revision, so it can be undone.
    |
    */

    'review' => env('LARECIPE_REVIEW', 'required'),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | This is the cache store LaRecipe keeps rendered pages, navigation and
    | search records in. If null, the app's default cache store is used.
    |
    */

    'cache' => [
        'store' => env('LARECIPE_CACHE_STORE'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Search
    |--------------------------------------------------------------------------
    |
    | The 'client' driver searches with MiniSearch in the reader's browser,
    | and 'server' uses a PHP ranker. Any other value is a custom driver
    | that your app registered with LaRecipe::extendSearch() at boot.
    |
    | A client index larger than budget_kb (gzipped) is reported, so a set
    | that outgrows the browser index knows to switch to server search.
    |
    */

    'search' => [
        'driver' => 'client',
        'budget_kb' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Snippet Includes
    |--------------------------------------------------------------------------
    |
    | Snippet includes such as <<< @/app/Billing/Invoice.php#refund resolve
    | inside this folder. Null means the app's root for repository sets.
    |
    | Database sets include nothing unless they set their own snippets.root.
    | Dotfiles such as .env, keys, storage/ and auth.json are never read.
    |
    */

    'snippets' => [
        'root' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Docs Migrations
    |--------------------------------------------------------------------------
    |
    | Docs migrations ship content changes to a database set as migration
    | files in this folder, and php artisan migrate then applies them.
    |
    | Docs migrations run only for sets that use the database source, and
    | skip_in_tests skips them when the test suite migrates its tables.
    |
    */

    'migrations' => [
        'path' => database_path('migrations/docs'),
        'skip_in_tests' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Source Models
    |--------------------------------------------------------------------------
    |
    | A database set may also set source.connection (a database connection),
    | source.cache (a persistent cache) and source.disk (the disk for its
    | assets), and each key below may point at a subclass of your own.
    |
    | Subclasses can add scopes, tenancy or observers to the tables that
    | LaRecipe uses, without changing any line of LaRecipe's own code.
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

    /*
    |--------------------------------------------------------------------------
    | Ask AI And The Docs Agent
    |--------------------------------------------------------------------------
    |
    | Ask AI needs laravel/ai and a provider key. The provider option takes
    | a laravel/ai provider name or a failover list tried in order, like
    | ['anthropic', 'openai'], or keyed by provider with a model each.
    | The model option applies only when a single provider is named.
    |
    | Answers run on your provider key. The rate_limit is answers per reader
    | ('10,1' is ten a minute), and daily_budget caps answers per set per
    | day. Set daily_budget to null to let answers run without any cap.
    |
    | The conversation a browser sends back is limited to max_messages turns,
    | max_question_length characters per question, and max_history_length
    | characters of the earlier turns in all, so a request stays small.
    |
    | The sync options set up php artisan larecipe:sync, the docs agent you
    | run on demand, never in CI. The timeout is in seconds, per request.
    |
    | The remote_token authenticates --remote runs against a production set's
    | MCP endpoint. Its user has to be one the manageLarecipe gate allows.
    |
    | The queue option names the queue for --queue runs, and the deny list
    | adds paths the agent may not read beyond the built-in ones, such
    | as .env and every dotfile, config/, storage/, vendor/ or keys.
    |
    */

    'ai' => [
        'ask' => [
            'enabled' => env('LARECIPE_ASK', false),
            'provider' => env('LARECIPE_AI_PROVIDER'),
            'model' => env('LARECIPE_AI_MODEL'),
            'max_steps' => 6,
            'rate_limit' => '10,1',
            'daily_budget' => 500,
            'max_messages' => 20,
            'max_question_length' => 2000,
            'max_history_length' => 20000,
        ],
        'sync' => [
            'provider' => env('LARECIPE_AI_PROVIDER'),
            'model' => env('LARECIPE_AI_MODEL'),
            'max_pages' => 20,
            'max_steps' => 30,
            'timeout' => 120,
            'remote_token' => env('LARECIPE_SYNC_TOKEN'),
            'deny' => [],
            'queue' => env('LARECIPE_SYNC_QUEUE'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Server
    |--------------------------------------------------------------------------
    |
    | Each set gets one web endpoint at /{prefix}/{path} (POST), and the local
    | stdio server (php artisan larecipe:mcp) serves every set of your app.
    |
    | The web.middleware option authenticates private sets: ['auth:sanctum']
    | for Sanctum tokens, or ['auth:api'] with Mcp::oauthRoutes() (OAuth).
    |
    | Access is checked again on every tool call. Author tools, which write
    | docs, are always on the local server, and on the web only for the
    | database sets that turn on author_tools in their web settings.
    |
    */

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

    /*
    |--------------------------------------------------------------------------
    | Agent Surfaces
    |--------------------------------------------------------------------------
    |
    | Each switch turns an agent surface on or off: each page's Markdown
    | twin, llms.txt, llms-full.txt, skill.md, the well-known skills,
    | the sitemap, and the ask endpoint for agents that lack MCP.
    |
    | The llms.txt indexes stay under llms_txt_limit characters, and bigger
    | ones split into one file per group. llms-full.txt splits by group
    | too, as soon as it gets past llms_full_limit characters in all.
    |
    | The larecipe:validate command warns when the agent Markdown of a page
    | is larger than markdown_budget characters, so pages stay readable.
    |
    */

    'agents' => [
        'markdown' => true,
        'llms_txt' => true,
        'llms_full' => true,
        'skill_md' => true,
        'well_known_skills' => true,
        'sitemap' => true,
        'ask_endpoint' => false,
        'llms_txt_limit' => 50000,
        'llms_full_limit' => 400000,
        'markdown_budget' => 50000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Page Feedback
    |--------------------------------------------------------------------------
    |
    | The "Was this page helpful?" widget and the submit_feedback MCP tool.
    | Both dispatch FeedbackSubmitted, for your app to forward anywhere,
    | and LaRecipe stores nothing. Turn it off to remove its endpoint.
    |
    */

    'feedback' => [
        'enabled' => true,
        'rate_limit' => '20,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Docs Assets
    |--------------------------------------------------------------------------
    |
    | The images and files that live with the docs, at /{prefix}/_assets/. The
    | rate limit is "requests,minutes" per reader, which is generous enough
    | for pages that carry many images, such as screenshots and diagrams.
    |
    */

    'assets' => [
        'rate_limit' => '600,1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Pre-Push Check
    |--------------------------------------------------------------------------
    |
    | The pre-push check runs on commands that push code or open a pull request.
    | Running larecipe:agents compiles these patterns into one small PHP file
    | in bootstrap/cache, so that plain shell commands never boot Laravel.
    | Set LARECIPE_HOOKS to off to turn the check off for one session.
    |
    */

    'hooks' => [
        'enabled' => env('LARECIPE_HOOKS', 'on') !== 'off',
        'push_commands' => [
            'git push',
            'gh pr create',
            'glab mr create',
            'hub pull-request',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Instructions For Coding Agents
    |--------------------------------------------------------------------------
    |
    | The LaRecipe block in AGENTS.md holds the before-you-push instructions
    | and a short docs index. When default_branch is null, LaRecipe reads
    | it from origin/HEAD, which works for nearly every Git repository.
    |
    */

    'instructions' => [
        'file' => 'AGENTS.md',
        'index' => true,
        'index_max_bytes' => 8192,
        'default_branch' => env('LARECIPE_DEFAULT_BRANCH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Live References
    |--------------------------------------------------------------------------
    |
    | Live references (<Route>, <Artisan>, <Model>, <Event>) read the public
    | shape of the app only. <Model> describes classes in these namespaces
    | and nothing else, while <Event> looks up short names in them too.
    |
    | <Route> shows a route's method, URI, middleware names and form request
    | fields and rule names, never rule arguments or the route's domain.
    |
    | The routes.allow list names the routes it may describe, by name or URI
    | ('orders.*', 'api/*'). Null means every route your app defines, but
    | not routes that packages in vendor/ register, except LaRecipe's.
    |
    */

    'references' => [
        'routes' => [
            'allow' => null,
        ],
        'models' => [
            'allow' => ['App\\Models\\*'],
        ],
        'events' => [
            'namespaces' => ['App\\Events'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reading UI
    |--------------------------------------------------------------------------
    |
    | Pages changed within fresh_days show a dot in the sidebar until the
    | reader opens them. Set it to 0 to turn the dots off for everyone.
    |
    | Set LARECIPE_UI_DEV to a Vite dev server URL, like localhost:5174, to
    | load the reader UI from it while you are working on the UI itself.
    |
    */

    'ui' => [
        'fresh_days' => 1,
        'dev_server' => env('LARECIPE_UI_DEV'),
    ],
];
