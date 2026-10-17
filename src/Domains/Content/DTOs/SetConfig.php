<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Support\Arr;

/**
 * A doc set's infrastructure settings from config/larecipe.php, with the
 * top-level defaults already merged in. Presentation lives in the Manifest.
 */
final readonly class SetConfig
{
    public function __construct(
        public string $key,
        public string $prefix,
        public ?string $domain,
        public array $middleware,
        public string $driver,
        public array $source,
        public string $accessMode,
        public string $review,
        public array $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $set  the set's own config
     * @param  array<string, mixed>  $defaults  the top-level config (review, search, ai, mcp, agents, ...)
     */
    public static function fromConfig(string $key, array $set, array $defaults): self
    {
        $settings = array_replace_recursive(
            array_intersect_key($defaults, array_flip(['review', 'cache', 'search', 'ai', 'mcp', 'agents', 'references', 'migrations'])),
            array_diff_key($set, array_flip(['route', 'source', 'access'])),
        );

        return new self(
            key: $key,
            prefix: trim((string) Arr::get($set, 'route.prefix', $key), '/'),
            domain: Arr::get($set, 'route.domain'),
            middleware: (array) Arr::get($set, 'route.middleware', ['web']),
            driver: (string) Arr::get($set, 'source.driver', 'repository'),
            source: (array) ($set['source'] ?? []),
            accessMode: (string) Arr::get($set, 'access.mode', 'public'),
            review: (string) ($settings['review'] ?? 'required'),
            settings: $settings,
        );
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->settings, $key, $default);
    }

    /**
     * Whether larecipe.json may declare integrations: analytics scripts that
     * run with every reader's session. A repository set's manifest is code
     * reviewed in git. A database set's can be changed from the admin UI and
     * MCP, so its analytics come from config/larecipe.php only.
     */
    public function allowsManifestIntegrations(): bool
    {
        return $this->driver === 'repository';
    }

    public function isRootMounted(): bool
    {
        return $this->prefix === '';
    }

    /** The URL path prefix with a leading slash, '' when mounted at the domain root. */
    public function basePath(): string
    {
        return $this->prefix === '' ? '' : '/'.$this->prefix;
    }
}
