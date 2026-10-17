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
use LaRecipe\Domains\Sources\Contracts\SourceFactory;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Sources\Exceptions\ReadOnlySource;
use LaRecipe\Support\Contracts\Clock;

/**
 * Builds and memoizes one content source per doc set, by driver name.
 *
 * The built-in drivers are "repository" and "database" (always wrapped in
 * CachedSource). Third parties may add their own with LaRecipe::extendSources(),
 * and must pass the published source contract tests.
 */
final class SourceManager implements ManifestProvider, SourceFactory, SourceResolver
{
    /** @var array<string, ContentSource> */
    private array $sources = [];

    /** @param array<string, SetConfig> $sets */
    public function __construct(
        private readonly Container $container,
        private readonly array $sets,
        private readonly SourceDrivers $drivers = new SourceDrivers,
    ) {}

    /** @param Closure(Container, SetConfig): ContentSource $factory */
    public function extend(string $driver, Closure $factory): void
    {
        $this->drivers->extend($driver, $factory);
        $this->sources = [];
    }

    public function for(string $set): ContentSource
    {
        if (isset($this->sources[$set])) {
            return $this->sources[$set];
        }

        $config = $this->sets[$set] ?? throw new InvalidArgumentException("LaRecipe has no doc set named [{$set}].");

        return $this->sources[$set] = $this->build($config);
    }

    /**
     * A source for the set with another driver or root, outside the memo:
     * the far end of a source move, or a bundle being imported.
     */
    public function make(string $set, string $driver, array $options = []): WritableContentSource
    {
        $config = $this->sets[$set] ?? throw new InvalidArgumentException("LaRecipe has no doc set named [{$set}].");
        $source = $this->build(new SetConfig(
            $config->key, $config->prefix, $config->domain, $config->middleware,
            $driver, $options, $config->accessMode, $config->review, $config->settings,
        ));

        if (! $source instanceof WritableContentSource) {
            throw ReadOnlySource::forSet($set, $driver);
        }

        return $source;
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

    private function build(SetConfig $config): ContentSource
    {
        $factory = $this->drivers->factory($config->driver);

        if ($factory === null && $config->driver === 'repository') {
            return new RepositorySource(
                $config->key,
                (string) ($config->source['path'] ?? base_path($config->key)),
                $this->container->make(FrontmatterParser::class),
                $this->container->make(ProvenanceIndexPath::class)->for($config->key),
                $this->container->make(Clock::class),
            );
        }

        if ($factory === null) {
            throw new InvalidArgumentException(
                "LaRecipe source driver [{$config->driver}] for set [{$config->key}] is not registered. Use 'repository' or 'database'."
            );
        }

        return $factory($this->container, $config);
    }
}
