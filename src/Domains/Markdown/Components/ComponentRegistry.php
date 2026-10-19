<?php

namespace LaRecipe\Domains\Markdown\Components;

/**
 * Every component tag LaRecipe will render: built-ins, live references, gate
 * blocks and the host's own components. Anything else is escaped text.
 */
final class ComponentRegistry
{
    /** @var array<string, ComponentDefinition> */
    private array $definitions = [];

    public function define(string $name, string $origin = 'host'): ComponentBuilder
    {
        $definition = $this->definitions[$name] ??= new ComponentDefinition($name, origin: $origin);

        return new ComponentBuilder($definition);
    }

    public function register(ComponentDefinition $definition): void
    {
        $this->definitions[$definition->name] = $definition;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): ?ComponentDefinition
    {
        return $this->definitions[$name] ?? null;
    }

    /** @return array<string, ComponentDefinition> */
    public function all(): array
    {
        ksort($this->definitions);

        return $this->definitions;
    }

    /** A hash of every definition, part of the render cache key. */
    public function version(): string
    {
        return substr(hash('xxh128', json_encode(array_map(fn (ComponentDefinition $d) => $d->toArray(), $this->all())) ?: ''), 0, 12);
    }
}
