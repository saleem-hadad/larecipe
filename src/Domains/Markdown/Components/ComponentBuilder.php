<?php

namespace LaRecipe\Domains\Markdown\Components;

use Closure;

/**
 * The fluent API behind LaRecipe::component('Name'). Every call updates the
 * registered definition, so the builder never has to be "finished".
 */
final class ComponentBuilder
{
    public function __construct(private readonly ComponentDefinition $definition) {}

    /** @param array<string, array<string, mixed>|string> $props name => spec, or name => type */
    public function props(array $props): self
    {
        $this->definition->props = array_map(fn ($spec) => is_string($spec) ? ['type' => $spec] : $spec, $props);

        return $this;
    }

    /** @param Closure(array, string, RenderedComponent): mixed $fallback returns HTML, a View or Htmlable */
    public function fallback(Closure $fallback): self
    {
        $this->definition->fallback = $fallback;

        return $this;
    }

    /** @param Closure(array, string): string $markdown */
    public function markdown(Closure $markdown): self
    {
        $this->definition->markdown = $markdown;

        return $this;
    }

    public function island(bool $island = true): self
    {
        $this->definition->island = $island;

        return $this;
    }

    public function description(string $description): self
    {
        $this->definition->description = $description;

        return $this;
    }

    public function definition(): ComponentDefinition
    {
        return $this->definition;
    }
}
