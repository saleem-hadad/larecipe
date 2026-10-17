<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Domains\Content\Support\SafeHref;

/**
 * The navigation of one version and locale, from navigation.json or derived
 * from folders and frontmatter when there is no navigation.json.
 *
 * Links a page may not carry (SafeHref) are left out while parsing, and kept
 * in $refusedLinks so validation and writes can say what was refused.
 */
final readonly class NavigationTree
{
    /**
     * @param  list<NavigationTab>  $tabs
     * @param  list<string>  $refusedLinks
     */
    public function __construct(
        public array $tabs,
        public bool $derived = false,
        public array $refusedLinks = [],
    ) {}

    /**
     * Accepts the documented shape ({"tabs": [...]}) and two shorthands:
     * {"groups": [...]} and a bare list of groups or pages.
     */
    public static function fromJson(array $data): self
    {
        if (isset($data['tabs'])) {
            return new self(array_values(array_map(fn (array $tab) => NavigationTab::fromJson($tab), array_filter((array) $data['tabs'], 'is_array'))), refusedLinks: self::refusedIn($data));
        }

        $items = isset($data['groups']) || isset($data['pages'])
            ? array_merge((array) ($data['groups'] ?? []), (array) ($data['pages'] ?? []))
            : (array_is_list($data) ? $data : []);

        return new self([new NavigationTab(null, NavigationNode::listFromJson($items))], refusedLinks: self::refusedIn($data));
    }

    /** @return list<string> every href in navigation JSON that SafeHref refuses */
    private static function refusedIn(array $data): array
    {
        $refused = [];

        array_walk_recursive($data, function (mixed $value, int|string $key) use (&$refused) {
            if ($key === 'href' && is_scalar($value) && SafeHref::of((string) $value) === null) {
                $refused[] = (string) $value;
            }
        });

        return $refused;
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
