<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\CatalogPage;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;

/**
 * The compact docs index that sits under the instructions in AGENTS.md
 * (ARCHITECTURE.md 13.4): one line per page, so an agent always has the map
 * and fetches pages on demand.
 *
 * It stays within a byte budget (about 8 KB): descriptions are shortened
 * first, then dropped, then the list is cut with a pointer to list_pages.
 */
final class DocsIndexRenderer
{
    private const DESCRIPTION_LIMIT = 120;

    /** @param list<CatalogPage> $pages */
    public function render(CatalogSet $set, array $pages, int $maxBytes): string
    {
        $pages = array_values(array_filter($pages, fn (CatalogPage $page) => ! $page->hidden));
        usort($pages, fn (CatalogPage $a, CatalogPage $b) => $this->sortKey($a) <=> $this->sortKey($b));

        $header = $this->header($set, $pages);

        foreach ([self::DESCRIPTION_LIMIT, 0] as $limit) {
            $lines = array_map(fn (CatalogPage $page) => $this->line($page, $limit), $pages);
            $text = $header.implode("\n", $lines);

            if (strlen($text) <= $maxBytes) {
                return $text;
            }
        }

        $kept = [];
        $budget = $maxBytes - strlen($header) - 120;

        foreach ($pages as $page) {
            $line = $this->line($page, 0);

            if ($budget - strlen($line) - 1 < 0) {
                break;
            }

            $budget -= strlen($line) + 1;
            $kept[] = $line;
        }

        $more = count($pages) - count($kept);
        $kept[] = "- ...and {$more} more pages: call `list_pages` on the `larecipe` MCP server for the rest.";

        return $header.implode("\n", $kept);
    }

    /** @param list<CatalogPage> $pages */
    private function header(CatalogSet $set, array $pages): string
    {
        $where = $set->isDatabase()
            ? 'with the `read_page` tool of the `larecipe` MCP server'
            : 'with the `read_page` tool of the `larecipe` MCP server, or open its file in '.$set->location();
        $version = $set->versioned ? " (version {$set->defaultVersion})" : '';
        $count = count($pages);

        return "### Docs index: {$set->name}{$version}\n\n"
            ."{$count} ".($count === 1 ? 'page' : 'pages').". One line per page: path, title, description. Read a page {$where}. Refresh this index with `php artisan larecipe:agents-md`.\n\n";
    }

    private function line(CatalogPage $page, int $descriptionLimit): string
    {
        $line = "- `{$page->ref->path}`: ".$this->clean($page->title);

        if ($descriptionLimit > 0 && $page->description !== null && trim($page->description) !== '') {
            $line .= '. '.$this->shorten($this->clean($page->description), $descriptionLimit);
        }

        return $line;
    }

    /**
     * A description within the limit: whole, or cut at its last full sentence,
     * or else at its last full word with an ellipsis. Never inside a word.
     */
    private function shorten(string $description, int $limit): string
    {
        if (mb_strlen($description) <= $limit) {
            return rtrim($description, '.').'.';
        }

        $cut = mb_substr($description, 0, $limit);

        if (preg_match('/^(.{'.intdiv($limit, 2).',}[.?])\s/u', $cut, $sentence)) {
            return $sentence[1];
        }

        $cut = mb_substr($description, 0, $limit - 1);

        if (mb_substr($description, $limit - 1, 1) !== ' ' && ($space = mb_strrpos($cut, ' ')) !== false) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, ' ,;:.').'…';
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    /** The landing page first, then folders in order, pages before their subfolders. */
    private function sortKey(CatalogPage $page): array
    {
        return [$page->ref->path === 'index' ? 0 : 1, substr_count($page->ref->path, '/') > 0 ? dirname($page->ref->path) : '', $page->ref->path];
    }
}
