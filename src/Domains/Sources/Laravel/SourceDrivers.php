<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Closure;
use Illuminate\Contracts\Container\Container;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Contracts\ContentSource;

/**
 * The registered source drivers, kept for the life of the app.
 *
 * SourceManager is scoped (one per request, job or command), and queue workers
 * and Octane forget scoped instances between jobs. Driver factories live here
 * instead, so a driver added with LaRecipe::extendSources() at boot is still
 * there for the hundredth job.
 */
final class SourceDrivers
{
    /** @var array<string, Closure(Container, SetConfig): ContentSource> */
    private array $factories = [];

    /** @param Closure(Container, SetConfig): ContentSource $factory */
    public function extend(string $driver, Closure $factory): void
    {
        $this->factories[$driver] = $factory;
    }

    /** @return (Closure(Container, SetConfig): ContentSource)|null */
    public function factory(string $driver): ?Closure
    {
        return $this->factories[$driver] ?? null;
    }
}
