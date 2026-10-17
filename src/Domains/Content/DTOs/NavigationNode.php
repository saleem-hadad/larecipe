<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Domains\Content\Enums\NavigationNodeType;

/**
 * One entry in a navigation tree: a page, a group of entries, or an external link.
 */
final readonly class NavigationNode
{
    /** @param list<NavigationNode> $children */
    public function __construct(
        public NavigationNodeType $type,
        public ?string $label = null,
        public ?string $path = null,
        public ?string $href = null,
        public ?string $icon = null,
        public array $children = [],
        public bool $collapsed = false,
    ) {}

    public static function page(string $path, ?string $label = null, ?string $icon = null): self
    {
        return new self(NavigationNodeType::Page, $label, PageRef::normalizePath($path), icon: $icon);
    }

    /** @param list<NavigationNode> $children */
    public static function group(string $label, array $children, ?string $icon = null, bool $collapsed = false): self
    {
        return new self(NavigationNodeType::Group, $label, children: $children, icon: $icon, collapsed: $collapsed);
    }

    public static function link(string $label, string $href, ?string $icon = null): self
    {
        return new self(NavigationNodeType::Link, $label, href: $href, icon: $icon);
    }

    /**
     * Parse one item of a navigation.json pages list.
     */
    public static function fromJson(mixed $item): ?self
    {
        if (is_string($item)) {
            return self::page($item);
        }

        if (! is_array($item)) {
            return null;
        }

        if (isset($item['group']) || isset($item['pages'])) {
            return self::group(
                (string) ($item['group'] ?? $item['label'] ?? ''),
                self::listFromJson((array) ($item['pages'] ?? [])),
                isset($item['icon']) ? (string) $item['icon'] : null,
                (bool) ($item['collapsed'] ?? false),
            );
        }

        if (isset($item['href'])) {
            return self::link((string) ($item['label'] ?? $item['href']), (string) $item['href'], isset($item['icon']) ? (string) $item['icon'] : null);
        }

        if (isset($item['page'])) {
            return self::page((string) $item['page'], isset($item['label']) ? (string) $item['label'] : null, isset($item['icon']) ? (string) $item['icon'] : null);
        }

        return null;
    }

    /** @return list<NavigationNode> */
    public static function listFromJson(array $items): array
    {
        return array_values(array_filter(array_map(fn ($item) => self::fromJson($item), $items)));
    }

    public function toJson(): string|array
    {
        return match ($this->type) {
            NavigationNodeType::Page => $this->label === null && $this->icon === null
                ? $this->path
                : array_filter(['page' => $this->path, 'label' => $this->label, 'icon' => $this->icon]),
            NavigationNodeType::Group => array_filter([
                'group' => $this->label,
                'icon' => $this->icon,
                'collapsed' => $this->collapsed ?: null,
                'pages' => array_map(fn (NavigationNode $child) => $child->toJson(), $this->children),
            ], fn ($value) => $value !== null),
            NavigationNodeType::Link => array_filter(['label' => $this->label, 'href' => $this->href, 'icon' => $this->icon]),
        };
    }

    /** @return list<string> every page path in this node and its children */
    public function pagePaths(): array
    {
        if ($this->type === NavigationNodeType::Page) {
            return [$this->path];
        }

        $paths = [];

        foreach ($this->children as $child) {
            array_push($paths, ...$child->pagePaths());
        }

        return $paths;
    }
}
