<?php

namespace LaRecipe\Domains\Content\Services;

use LaRecipe\Domains\Content\DTOs\NavigationNode;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Content\Enums\NavigationNodeType;

/**
 * Turns a navigation tree into what readers see: page titles filled in, pages
 * the reader cannot see (or that do not exist) left out, empty groups dropped.
 *
 * The output is plain arrays because it goes straight into the boot payload,
 * the prerender and llms.txt.
 */
final class NavigationResolver
{
    /**
     * @param  array<string, PageSummary>  $visible  pages the reader may see, keyed by path
     * @return list<array{label: ?string, href: ?string, icon: ?string, items: list<array>}>
     */
    public function resolve(NavigationTree $tree, array $visible): array
    {
        $tabs = [];

        foreach ($tree->tabs as $tab) {
            $items = $tab->href === null ? $this->items($tab->items, $visible) : [];

            if ($tab->href === null && $items === []) {
                continue;
            }

            $tabs[] = ['label' => $tab->label, 'href' => $tab->href, 'icon' => $tab->icon, 'items' => $items];
        }

        return $tabs;
    }

    /**
     * The pages in reading order, for previous and next links.
     *
     * @return list<string>
     */
    public function sequence(array $tabs): array
    {
        $paths = [];
        $walk = function (array $items) use (&$walk, &$paths) {
            foreach ($items as $item) {
                if ($item['type'] === 'page') {
                    $paths[] = $item['path'];
                } elseif ($item['type'] === 'group') {
                    $walk($item['items']);
                }
            }
        };

        foreach ($tabs as $tab) {
            $walk($tab['items']);
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<NavigationNode>  $nodes
     * @param  array<string, PageSummary>  $visible
     */
    private function items(array $nodes, array $visible): array
    {
        $items = [];

        foreach ($nodes as $node) {
            $item = match ($node->type) {
                NavigationNodeType::Page => isset($visible[$node->path]) ? [
                    'type' => 'page',
                    'path' => $node->path,
                    'title' => $node->label ?? $visible[$node->path]->title,
                    'icon' => $node->icon ?? $visible[$node->path]->icon,
                    'badge' => $visible[$node->path]->badge,
                ] : null,
                NavigationNodeType::Group => ($children = $this->items($node->children, $visible)) === [] ? null : [
                    'type' => 'group',
                    'label' => (string) $node->label,
                    'icon' => $node->icon,
                    'collapsed' => $node->collapsed,
                    'items' => $children,
                ],
                NavigationNodeType::Link => [
                    'type' => 'link',
                    'label' => (string) $node->label,
                    'href' => (string) $node->href,
                    'icon' => $node->icon,
                ],
            };

            if ($item !== null) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
