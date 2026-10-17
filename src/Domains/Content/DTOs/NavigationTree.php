<?php

namespace LaRecipe\Domains\Content\DTOs;

/**
 * The navigation of one version and locale, from navigation.json or derived
 * from folders and frontmatter when there is no navigation.json.
 */
final readonly class NavigationTree
{
    /** @param list<NavigationTab> $tabs */
    public function __construct(
        public array $tabs,
        public bool $derived = false,
    ) {}

    /**
     * Accepts the documented shape ({"tabs": [...]}) and two shorthands:
     * {"groups": [...]} and a bare list of groups or pages.
     */
    public static function fromJson(array $data): self
    {
        if (isset($data['tabs'])) {
            return new self(array_values(array_map(fn (array $tab) => NavigationTab::fromJson($tab), array_filter((array) $data['tabs'], 'is_array'))));
        }

        $items = isset($data['groups']) || isset($data['pages'])
            ? array_merge((array) ($data['groups'] ?? []), (array) ($data['pages'] ?? []))
            : (array_is_list($data) ? $data : []);

        return new self([new NavigationTab(null, NavigationNode::listFromJson($items))]);
    }

    public function toJson(): array
    {
        if (count($this->tabs) === 1 && $this->tabs[0]->label === null) {
            return ['groups' => array_map(fn (NavigationNode $n) => $n->toJson(), $this->tabs[0]->items)];
        }

        return ['tabs' => array_map(fn (NavigationTab $tab) => $tab->toJson(), $this->tabs)];
    }

    /** @return list<string> every page path in reading order */
    public function pagePaths(): array
    {
        $paths = [];

        foreach ($this->tabs as $tab) {
            foreach ($tab->items as $item) {
                array_push($paths, ...$item->pagePaths());
            }
        }

        return array_values(array_unique($paths));
    }
}
