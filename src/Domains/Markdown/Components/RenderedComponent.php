<?php

namespace LaRecipe\Domains\Markdown\Components;

/**
 * Context handed to a component's fallback: the page being rendered and helpers.
 */
final readonly class RenderedComponent
{
    public function __construct(
        public string $name,
        public array $props,
        public ?string $set = null,
        public ?string $version = null,
        public ?string $locale = null,
        public ?string $path = null,
    ) {}
}
