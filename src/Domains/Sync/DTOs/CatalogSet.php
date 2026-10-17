<?php

namespace LaRecipe\Domains\Sync\DTOs;

use LaRecipe\Domains\Sync\Enums\ReviewMode;

/**
 * One doc set, described for drift detection and for the agent instructions:
 * where its content lives and how changes to it travel with the code.
 */
final readonly class CatalogSet
{
    /**
     * @param  ?string  $docsPath  the content folder relative to the project root; null for database sets
     * @param  string  $migrationsPath  where docs migrations live, relative to the project root
     * @param  list<string>  $versions
     * @param  list<string>  $locales
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $driver,
        public ?string $docsPath,
        public string $migrationsPath,
        public array $versions,
        public string $defaultVersion,
        public array $locales,
        public string $defaultLocale,
        public ReviewMode $review,
        public bool $versioned = false,
    ) {}

    public function isDatabase(): bool
    {
        return $this->driver === 'database';
    }

    /** How a person reads where the docs live: "`docs/`" or "the database". */
    public function location(): string
    {
        return $this->isDatabase() || $this->docsPath === null
            ? 'the database'
            : '`'.rtrim($this->docsPath, '/').'/`';
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'driver' => $this->driver,
            'docs_path' => $this->docsPath,
            'migrations_path' => $this->migrationsPath,
            'versions' => $this->versions,
            'default_version' => $this->defaultVersion,
            'locales' => $this->locales,
            'default_locale' => $this->defaultLocale,
            'review' => $this->review->value,
        ];
    }
}
