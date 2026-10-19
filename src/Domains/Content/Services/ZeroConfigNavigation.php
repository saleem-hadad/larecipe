<?php

namespace LaRecipe\Domains\Content\Services;

use LaRecipe\Domains\Content\DTOs\NavigationNode;
use LaRecipe\Domains\Content\DTOs\NavigationTab;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\PageSummary;

/**
 * Derives navigation from folders and frontmatter when a version has no navigation.json.
 *
 * Top-level pages come first (the index page leads), then one group per folder.
 * A folder's label is its index page's title, or the folder name humanized.
 * Pages sort by frontmatter `order`, then title. Hidden pages are routable but unlisted.
 */
final class ZeroConfigNavigation
{
    /** @param iterable<PageSummary> $pages */
    public function build(iterable $pages): NavigationTree
    {
        $root = ['pages' => [], 'folders' => [], 'index' => null];

        foreach ($pages as $page) {
            $segments = $page->ref->path === 'index' ? [] : explode('/', $page->ref->path);
            $isFolderIndex = false;
            $node = &$root;

            foreach ($segments as $i => $segment) {
                $last = $i === count($segments) - 1;

                if ($last) {
                    break;
                }

                $node['folders'][$segment] ??= ['pages' => [], 'folders' => [], 'index' => null];
                $node = &$node['folders'][$segment];
            }

            $leaf = $segments === [] ? null : end($segments);

            if ($leaf !== null && isset($node['folders'][$leaf])) {
                $isFolderIndex = true;
            }

            if ($page->ref->path === 'index') {
                $root['index'] = $page;
            } elseif ($isFolderIndex) {
                $node['folders'][$leaf]['index'] = $page;
            } else {
                $node['pages'][$leaf] = $page;
            }

            unset($node);
        }

        // A page "guides.md" next to a folder "guides/" is that folder's index.
        $this->promoteFolderIndexes($root);

        $items = [];

        if ($root['index'] !== null && ! $root['index']->hidden) {
            $items[] = NavigationNode::page($root['index']->ref->path);
        }

        foreach ($this->sortPages($root['pages']) as $page) {
            $items[] = NavigationNode::page($page->ref->path);
        }

        foreach ($this->sortFolders($root['folders']) as $name => $folder) {
            $group = $this->group($name, $folder);

            if ($group !== null) {
                $items[] = $group;
            }
        }

        return new NavigationTree([new NavigationTab(null, $items)], derived: true);
    }

    private function group(string $name, array $folder): ?NavigationNode
    {
        if ($folder['index'] !== null && $folder['index']->hidden) {
            return null;
        }

        $children = [];

        if ($folder['index'] !== null) {
            $children[] = NavigationNode::page($folder['index']->ref->path);
        }

        foreach ($this->sortPages($folder['pages']) as $page) {
            $children[] = NavigationNode::page($page->ref->path);
        }

        foreach ($this->sortFolders($folder['folders']) as $childName => $child) {
            $nested = $this->group($childName, $child);

            if ($nested !== null) {
                $children[] = $nested;
            }
        }

        if ($children === []) {
            return null;
        }

        return NavigationNode::group(
            $folder['index']?->title ?? $this->humanize($name),
            $children,
            $folder['index']?->icon,
        );
    }

    private function promoteFolderIndexes(array &$node): void
    {
        foreach ($node['folders'] as $name => &$folder) {
            if ($folder['index'] === null && isset($node['pages'][$name])) {
                $folder['index'] = $node['pages'][$name];
                unset($node['pages'][$name]);
            }

            $this->promoteFolderIndexes($folder);
        }
    }

    /** @param array<string, PageSummary> $pages @return list<PageSummary> */
    private function sortPages(array $pages): array
    {
        $pages = array_values(array_filter($pages, fn (PageSummary $page) => ! $page->hidden));

        usort($pages, fn (PageSummary $a, PageSummary $b) => [$a->order ?? PHP_INT_MAX, $a->title] <=> [$b->order ?? PHP_INT_MAX, $b->title]);

        return $pages;
    }

    private function sortFolders(array $folders): array
    {
        uksort($folders, function (string $a, string $b) use ($folders) {
            $orderA = $folders[$a]['index']?->order ?? PHP_INT_MAX;
            $orderB = $folders[$b]['index']?->order ?? PHP_INT_MAX;

            return [$orderA, $folders[$a]['index']?->title ?? $a] <=> [$orderB, $folders[$b]['index']?->title ?? $b];
        });

        return $folders;
    }

    private function humanize(string $name): string
    {
        return ucfirst(str_replace(['-', '_'], ' ', preg_replace('/^\d+[-_.]/', '', $name) ?? $name));
    }
}
