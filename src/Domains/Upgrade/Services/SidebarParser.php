<?php

namespace LaRecipe\Domains\Upgrade\Services;

use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;

/**
 * Turns a v2 sidebar (a version's index.md: a nested list of "## Group"
 * headings and links) into that version's navigation.json.
 *
 *   - ## Get Started
 *       - [Overview](/{{route}}/{{version}}/overview)
 *
 * becomes {"groups": [{"group": "Get Started", "pages": ["overview"]}]}. A label
 * that differs from the page's own title is kept, so the sidebar reads as before.
 */
final class SidebarParser
{
    /**
     * @param  array<string, string>  $titles  page path => the page's title, for the label check
     * @return array{navigation: array, entries: list<ReportEntry>}
     */
    public function parse(string $markdown, string $version, V2Links $links, array $titles, string $file): array
    {
        $items = [];
        $entries = [];
        $group = null;

        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $number => $line) {
            if (trim($line) === '') {
                continue;
            }

            if (preg_match('/^\s*[-*+]\s+#{1,6}\s+(.+?)\s*#*\s*$/', $line, $matches)) {
                $items[] = ['group' => $this->plain($matches[1]), 'pages' => []];
                $group = array_key_last($items);

                continue;
            }

            if (preg_match('/^(\s*)[-*+]\s+\[(.+?)\]\((.+?)\)/', $line, $matches)) {
                $item = $this->item($this->plain($matches[2]), trim($matches[3]), $version, $links, $titles);

                if ($group !== null && strlen($matches[1]) > 0) {
                    $items[$group]['pages'][] = $item;
                } else {
                    $items[] = $item;
                    $group = null;
                }

                continue;
            }

            $entries[] = ReportEntry::manual('navigation', 'This sidebar line is not a group heading or a link, so it was left out of navigation.json: '.trim($line), $file, $number + 1);
        }

        $pages = count(array_filter($items, 'is_string')) + array_sum(array_map(fn ($item) => is_array($item) ? count($item['pages'] ?? []) : 0, $items));
        $entries[] = ReportEntry::changed('navigation', "The sidebar became navigation.json ({$pages} entries).", $file);

        return ['navigation' => ['$schema' => 'https://larecipe.saleem.dev/schemas/3.x/navigation.json', 'groups' => array_values($items)], 'entries' => $entries];
    }

    /** @param array<string, string> $titles */
    private function item(string $label, string $url, string $version, V2Links $links, array $titles): string|array
    {
        $path = $links->pagePath($url, $version);

        if ($path === null) {
            return ['label' => $label, 'href' => str_replace(['{{version}}', '{{ version }}'], $version, $url)];
        }

        return isset($titles[$path]) && $titles[$path] === $label ? $path : ['page' => $path, 'label' => $label];
    }

    private function plain(string $text): string
    {
        return trim((string) preg_replace('/[*_`]/', '', $text));
    }
}
