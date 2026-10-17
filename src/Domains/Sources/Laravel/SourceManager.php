<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Closure;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use LaRecipe\Domains\Content\Contracts\ManifestProvider;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;

/**
 * Builds and memoizes one content source per doc set, by driver name.
 *
 * The built-in drivers are "repository" and "database". Third parties may add
 * their own with LaRecipe::extendSources(), and must pass the published
 * source contract tests.
 */
final class SourceManager implements ManifestProvider, SourceResolver
{
    /** @var array<string, ContentSource> */
    private array $sources = [];

    /** @var array<string, Closure(Container, SetConfig): ContentSource> */
    private array $drivers = [];

    /** @param array<string, SetConfig> $sets */
    public function __construct(
        private readonly Container $container,
        private readonly array $sets,
    ) {
        $this->drivers['repository'] = fn (Container $app, SetConfig $set) => new RepositorySource(
            $set->key,
            (string) ($set->source['path'] ?? base_path($set->key)),
            $app->make(FrontmatterParser::class),
        );
    }

    /** @param Closure(Container, SetConfig): ContentSource $factory */
    public function extend(string $driver, Closure $factory): void
    {
        $this->drivers[$driver] = $factory;
        $this->sources = [];
    }

    public function for(string $set): ContentSource
    {
        if (isset($this->sources[$set])) {
            return $this->sources[$set];
        }

        $config = $this->sets[$set] ?? throw new InvalidArgumentException("LaRecipe has no doc set named [{$set}].");
        $factory = $this->drivers[$config->driver] ?? throw new InvalidArgumentException(
            "LaRecipe source driver [{$config->driver}] for set [{$set}] is not registered. Use 'repository' or 'database'."
        );

        return $this->sources[$set] = $factory($this->container, $config);
    }

    public function manifestFor(string $set): ?Manifest
    {
        return $this->for($set)->manifest();
    }

    public function forget(?string $set = null): void
    {
        if ($set === null) {
            $this->sources = [];
        } else {
            unset($this->sources[$set]);
        }
    }
}
