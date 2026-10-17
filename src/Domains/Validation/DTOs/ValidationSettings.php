<?php

namespace LaRecipe\Domains\Validation\DTOs;

/**
 * The facts about a set's setup that rules need, gathered once by the adapter
 * so the rules themselves never read configuration.
 */
final readonly class ValidationSettings
{
    /**
     * @param  list<string>  $knownComponents  every component tag the renderer knows
     * @param  ?string  $snippetRoot  the folder snippet includes may read from, null when includes are off
     * @param  ?string  $cacheStore  the cache driver behind the set (database sets need a persistent one)
     */
    public function __construct(
        public string $prefix = '',
        public string $driver = 'repository',
        public ?string $cacheStore = null,
        public string $searchDriver = 'client',
        public int $markdownBudget = 50000,
        public int $searchBudget = 300000,
        public array $knownComponents = [],
        public ?string $snippetRoot = null,
        public bool $testing = false,
    ) {}
}
