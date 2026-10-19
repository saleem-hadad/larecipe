<?php

namespace LaRecipe\Domains\Search\Services;

use Closure;
use LaRecipe\Domains\Search\Contracts\SearchDriver;

/**
 * Which search a doc set uses. "client" (the default: MiniSearch in the
 * browser, the PHP ranker for agents) and "server" (the PHP ranker for
 * everyone) are built in; any other name is a custom driver registered with
 * LaRecipe::extendSearch().
 */
final class SearchDrivers
{
    public const CLIENT = 'client';

    public const SERVER = 'server';

    /** @var array<string, ?SearchDriver> */
    private array $drivers = [];

    /**
     * @param  Closure(string $set): string  $nameFor  the configured driver name of a set
     * @param  Closure(string $set, string $name): SearchDriver  $make  builds a custom driver
     */
    public function __construct(
        private readonly Closure $nameFor,
        private readonly Closure $make,
    ) {}

    public function name(string $set): string
    {
        return ($this->nameFor)($set);
    }

    public function isBuiltIn(string $set): bool
    {
        return in_array($this->name($set), [self::CLIENT, self::SERVER], true);
    }

    /** The custom driver of a set, or null when it uses a built-in one. */
    public function for(string $set): ?SearchDriver
    {
        if (! array_key_exists($set, $this->drivers)) {
            $this->drivers[$set] = $this->isBuiltIn($set) ? null : ($this->make)($set, $this->name($set));
        }

        return $this->drivers[$set];
    }
}
