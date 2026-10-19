<?php

namespace LaRecipe\Domains\Content\Services;

use LaRecipe\Domains\Content\Contracts\ManifestProvider;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\Exceptions\UnknownDocSet;

/**
 * Every doc set in the app: its config from config/larecipe.php and its manifest
 * from its source. Manifests are loaded lazily and memoized for the lifetime of
 * the registry (one request, or one command).
 */
final class SetRegistry
{
    /** @var array<string, DocSet> */
    private array $resolved = [];

    /**
     * @param  array<string, SetConfig>  $configs
     */
    public function __construct(
        private readonly array $configs,
        private readonly string $default,
        private readonly ManifestProvider $manifests,
    ) {}

    public function has(string $key): bool
    {
        return isset($this->configs[$key]);
    }

    public function get(string $key): DocSet
    {
        if (! $this->has($key)) {
            throw UnknownDocSet::named($key, array_keys($this->configs));
        }

        return $this->resolved[$key] ??= new DocSet(
            $key,
            $this->configs[$key],
            $this->manifests->manifestFor($key) ?? Manifest::empty(ucfirst($key)),
        );
    }

    public function default(): DocSet
    {
        return $this->get($this->has($this->default) ? $this->default : array_key_first($this->configs));
    }

    public function config(string $key): SetConfig
    {
        if (! $this->has($key)) {
            throw UnknownDocSet::named($key, array_keys($this->configs));
        }

        return $this->configs[$key];
    }

    /** @return array<string, SetConfig> */
    public function configs(): array
    {
        return $this->configs;
    }

    /** @return list<DocSet> */
    public function all(): array
    {
        return array_values(array_map(fn (string $key) => $this->get($key), array_keys($this->configs)));
    }

    /** Forget loaded manifests, after content changed. */
    public function flush(): void
    {
        $this->resolved = [];
    }
}
