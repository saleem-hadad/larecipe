<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Domains\Content\Support\SafeHref;

/**
 * A top-level navigation tab. Most sets have one implicit tab with no label.
 * A link tab whose href a page may not carry (SafeHref) loses its href.
 */
final readonly class NavigationTab
{
    /** @param list<NavigationNode> $items */
    public function __construct(
        public ?string $label,
        public array $items = [],
        public ?string $href = null,
        public ?string $icon = null,
    ) {}

    public static function fromJson(array $data): self
    {
        return new self(
            label: isset($data['label']) ? (string) $data['label'] : null,
            items: NavigationNode::listFromJson(array_merge((array) ($data['groups'] ?? []), (array) ($data['pages'] ?? []))),
            href: isset($data['href']) ? SafeHref::of((string) $data['href']) : null,
            icon: isset($data['icon']) ? (string) $data['icon'] : null,
        );
    }

    public function toJson(): array
    {
        return array_filter([
            'label' => $this->label,
            'href' => $this->href,
            'icon' => $this->icon,
            'groups' => $this->href === null ? array_map(fn (NavigationNode $n) => $n->toJson(), $this->items) : null,
        ], fn ($value) => $value !== null);
    }
}
