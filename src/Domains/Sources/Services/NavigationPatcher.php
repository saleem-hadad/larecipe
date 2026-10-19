<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Content\DTOs\NavigationNode;
use LaRecipe\Domains\Content\DTOs\NavigationTab;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Enums\NavigationNodeType;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;

/**
 * Applies small, readable edits to a navigation tree, so an agent can add one
 * page without resending the whole navigation.json.
 *
 * A patch is a list of operations, applied in order:
 *
 *   ['add' => 'billing/exports', 'group' => 'Billing', 'after' => 'billing/overview', 'label' => null, 'icon' => null, 'tab' => null]
 *   ['move' => 'billing/exports', 'group' => 'Reports', 'before' => 'reports/index']
 *   ['remove' => 'billing/legacy']
 *   ['rename' => 'billing/exports', 'to' => 'billing/csv-exports']
 *
 * `add` places a page (removing it from wherever it was first, so it is safe to
 * repeat). A `group` that does not exist yet is created at the end of the tab.
 * `after` and `before` name a sibling page path or group label; without them the
 * page goes last. `tab` names a tab by label; the first tab is the default.
 */
final class NavigationPatcher
{
    /** @param list<array<string, mixed>> $patch */
    public function apply(NavigationTree $tree, array $patch): NavigationTree
    {
        $tabs = $tree->tabs === [] ? [new NavigationTab(null, [])] : $tree->tabs;

        foreach (array_values($patch) as $index => $operation) {
            if (! is_array($operation)) {
                throw InvalidContentChange::invalidPatch('operation #'.($index + 1).' is not an object.');
            }

            $tabs = match (true) {
                isset($operation['add']) => $this->add($tabs, (string) $operation['add'], $operation),
                isset($operation['move']) => $this->move($tabs, (string) $operation['move'], $operation),
                isset($operation['remove']) => $this->without($tabs, PageRef::normalizePath((string) $operation['remove'])),
                isset($operation['rename']) => $this->rename($tabs, (string) $operation['rename'], (string) ($operation['to'] ?? '')),
                default => throw InvalidContentChange::invalidPatch('operation #'.($index + 1).' needs one of add, move, remove or rename.'),
            };
        }

        return new NavigationTree($tabs);
    }

    /** @param list<NavigationTab> $tabs @return list<NavigationTab> */
    private function add(array $tabs, string $path, array $operation): array
    {
        $path = PageRef::normalizePath($path);
        $node = NavigationNode::page(
            $path,
            isset($operation['label']) ? (string) $operation['label'] : null,
            isset($operation['icon']) ? (string) $operation['icon'] : null,
        );

        return $this->place($this->without($tabs, $path), $node, $operation);
    }

    /** @param list<NavigationTab> $tabs @return list<NavigationTab> */
    private function move(array $tabs, string $path, array $operation): array
    {
        $path = PageRef::normalizePath($path);
        $node = $this->find($tabs, $path) ?? throw InvalidContentChange::invalidPatch("[{$path}] is not in the navigation, so it cannot move. Use add.");

        return $this->place($this->without($tabs, $path), $node, $operation);
    }

    /** @param list<NavigationTab> $tabs @return list<NavigationTab> */
    private function rename(array $tabs, string $from, string $to): array
    {
        if (trim($to) === '') {
            throw InvalidContentChange::invalidPatch('rename needs a "to" path.');
        }

        $from = PageRef::normalizePath($from);
        $to = PageRef::normalizePath($to);

        return array_map(fn (NavigationTab $tab) => new NavigationTab($tab->label, $this->renameIn($tab->items, $from, $to), $tab->href, $tab->icon), $tabs);
    }

    /** @param list<NavigationTab> $tabs @return list<NavigationTab> */
    private function without(array $tabs, string $path): array
    {
        return array_map(fn (NavigationTab $tab) => new NavigationTab($tab->label, $this->removeFrom($tab->items, $path), $tab->href, $tab->icon), $tabs);
    }

    /** @param list<NavigationTab> $tabs @return list<NavigationTab> */
    private function place(array $tabs, NavigationNode $node, array $operation): array
    {
        $index = $this->tabIndex($tabs, isset($operation['tab']) ? (string) $operation['tab'] : null);

        if (! isset($tabs[$index])) {
            $tabs[$index] = new NavigationTab((string) $operation['tab'], []);
        }

        $tab = $tabs[$index];

        if (isset($operation['group'])) {
            [$items, $placed] = $this->insertIntoGroup($tab->items, (string) $operation['group'], $node, $operation);

            if (! $placed) {
                $items = [...$tab->items, NavigationNode::group((string) $operation['group'], [$node])];
            }
        } else {
            $items = $this->insertRelative($tab->items, $node, $operation);
        }

        $tabs[$index] = new NavigationTab($tab->label, $items, $tab->href, $tab->icon);

        return array_values($tabs);
    }

    /** @param list<NavigationTab> $tabs */
    private function tabIndex(array $tabs, ?string $label): int
    {
        foreach ($tabs as $index => $tab) {
            if ($label === null ? $tab->href === null : strcasecmp((string) $tab->label, $label) === 0) {
                return $index;
            }
        }

        return $label === null ? 0 : count($tabs);
    }

    /**
     * @param  list<NavigationNode>  $items
     * @return array{0: list<NavigationNode>, 1: bool}
     */
    private function insertIntoGroup(array $items, string $label, NavigationNode $node, array $operation): array
    {
        foreach ($items as $index => $item) {
            if ($item->type !== NavigationNodeType::Group) {
                continue;
            }

            if (strcasecmp((string) $item->label, $label) === 0) {
                $items[$index] = $this->withChildren($item, $this->insertRelative($item->children, $node, $operation));

                return [$items, true];
            }

            [$children, $placed] = $this->insertIntoGroup($item->children, $label, $node, $operation);

            if ($placed) {
                $items[$index] = $this->withChildren($item, $children);

                return [$items, true];
            }
        }

        return [$items, false];
    }

    /**
     * @param  list<NavigationNode>  $items
     * @return list<NavigationNode>
     */
    private function insertRelative(array $items, NavigationNode $node, array $operation): array
    {
        foreach (['after' => 1, 'before' => 0] as $key => $offset) {
            if (! isset($operation[$key])) {
                continue;
            }

            $anchor = (string) $operation[$key];

            foreach ($items as $index => $item) {
                if ($this->matches($item, $anchor)) {
                    array_splice($items, $index + $offset, 0, [$node]);

                    return array_values($items);
                }
            }
        }

        $items[] = $node;

        return array_values($items);
    }

    private function matches(NavigationNode $item, string $anchor): bool
    {
        return match ($item->type) {
            NavigationNodeType::Page => $item->path === PageRef::normalizePath($anchor),
            NavigationNodeType::Group, NavigationNodeType::Link => strcasecmp((string) $item->label, $anchor) === 0,
        };
    }

    /** @param list<NavigationTab> $tabs */
    private function find(array $tabs, string $path): ?NavigationNode
    {
        $search = function (array $items) use (&$search, $path): ?NavigationNode {
            foreach ($items as $item) {
                if ($item->type === NavigationNodeType::Page && $item->path === $path) {
                    return $item;
                }

                if ($item->type === NavigationNodeType::Group && ($found = $search($item->children)) !== null) {
                    return $found;
                }
            }

            return null;
        };

        foreach ($tabs as $tab) {
            if (($found = $search($tab->items)) !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<NavigationNode>  $items
     * @return list<NavigationNode>
     */
    private function removeFrom(array $items, string $path): array
    {
        $result = [];

        foreach ($items as $item) {
            if ($item->type === NavigationNodeType::Page && $item->path === $path) {
                continue;
            }

            $result[] = $item->type === NavigationNodeType::Group ? $this->withChildren($item, $this->removeFrom($item->children, $path)) : $item;
        }

        return $result;
    }

    /**
     * @param  list<NavigationNode>  $items
     * @return list<NavigationNode>
     */
    private function renameIn(array $items, string $from, string $to): array
    {
        return array_map(fn (NavigationNode $item) => match (true) {
            $item->type === NavigationNodeType::Page && $item->path === $from => new NavigationNode(NavigationNodeType::Page, $item->label, $to, null, $item->icon),
            $item->type === NavigationNodeType::Group => $this->withChildren($item, $this->renameIn($item->children, $from, $to)),
            default => $item,
        }, $items);
    }

    /** @param list<NavigationNode> $children */
    private function withChildren(NavigationNode $group, array $children): NavigationNode
    {
        return new NavigationNode(NavigationNodeType::Group, $group->label, null, null, $group->icon, array_values($children), $group->collapsed);
    }
}
