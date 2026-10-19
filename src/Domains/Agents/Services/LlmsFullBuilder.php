<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\DTOs\IndexEntry;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * Builds a version's llms-full.txt: the agent Markdown of every visible page,
 * in reading order, each page with its own YAML header (title, description, URL).
 *
 * There is no whole-site dump: a file covers one version and stays under a cap
 * (400,000 characters by default). Past the cap, each top-level group moves to
 * {version}/{group}/llms-full.txt, and a file that still overflows ends with
 * links to the pages it left out, so nothing is silently cut.
 *
 * The result is a list of chunks so the controller can stream it.
 */
final class LlmsFullBuilder
{
    public const DEFAULT_LIMIT = 400000;

    public function __construct(
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly OutlineBuilder $outlines,
        private readonly SetDescriber $describe,
        private readonly LlmsTxtWriter $writer,
    ) {}

    /** @return list<string>|null chunks, or null when the version or group is hidden or unknown */
    public function build(DocSet $set, string $version, string $locale, ?string $group = null, int $limit = self::DEFAULT_LIMIT): ?array
    {
        if ($set->version($version) === null || ! $this->docs->canViewVersion($set, $version)) {
            return null;
        }

        $outline = $this->outlines->build($set, $version, $locale);

        if ($group !== null) {
            $section = $outline->section($group);

            return $section === null ? null : $this->file($set, $version, $locale, $section->allEntries(), $limit, $section->heading);
        }

        $all = $outline->entries();
        $pages = $this->pages($set, $version, $locale, $all);

        if ($this->length($pages) <= $limit) {
            return $this->assemble($set, $version, $locale, $pages, $all, $limit, null, []);
        }

        $inline = $outline->optional;
        $groups = [];

        foreach ($outline->sections as $section) {
            if ($section->slug === null) {
                $inline = [...$section->entries, ...$inline];
            } else {
                $groups[] = new IndexEntry(
                    title: $section->heading,
                    url: $this->links->llmsFull($set, $version, $this->localeArgument($set, $locale), $section->slug),
                    description: count($section->allEntries()).' pages.',
                );
            }
        }

        return $this->assemble($set, $version, $locale, $this->pages($set, $version, $locale, $inline), $inline, $limit, null, $groups);
    }

    /** @param list<IndexEntry> $entries */
    private function file(DocSet $set, string $version, string $locale, array $entries, int $limit, string $heading): array
    {
        return $this->assemble($set, $version, $locale, $this->pages($set, $version, $locale, $entries), $entries, $limit, $heading, []);
    }

    /**
     * @param  array<string, string>  $pages  path => agent Markdown
     * @param  list<IndexEntry>  $entries
     * @param  list<IndexEntry>  $groups  split files, listed at the end
     * @return list<string>
     */
    private function assemble(DocSet $set, string $version, string $locale, array $pages, array $entries, int $limit, ?string $heading, array $groups): array
    {
        $title = $this->describe->title($set, $version, $locale).($heading !== null ? ': '.$heading : '').': full text';
        $intro = $this->writer->write($title, $this->describe->summary($set), [
            'This file holds the Markdown of '.count($pages).' pages, in reading order. Each page starts with its own header (title, description, URL). '
            .'The index is at `'.$this->links->llms($set, $version, $this->localeArgument($set, $locale)).'`.',
        ], []);

        $chunks = [$intro];
        $used = mb_strlen($intro);
        $left = [];

        foreach ($entries as $entry) {
            $markdown = $pages[$entry->path] ?? null;

            if ($markdown === null) {
                continue;
            }

            $chunk = "\n".rtrim($markdown)."\n";

            if ($left !== [] || $used + mb_strlen($chunk) > $limit) {
                $left[] = $entry;

                continue;
            }

            $chunks[] = $chunk;
            $used += mb_strlen($chunk);
        }

        if ($groups !== []) {
            $chunks[] = "\n".$this->section('More pages, one file per section', 'This version is too large for one file. Each section has its own:', $groups);
        }

        if ($left !== []) {
            $chunks[] = "\n".$this->section('Pages not included', 'This file reached its size limit. Read these pages at their Markdown URLs:', $left);
        }

        return $chunks;
    }

    /** @param list<IndexEntry> $entries */
    private function section(string $heading, string $lead, array $entries): string
    {
        $lines = ['## '.$heading, '', $lead, ''];

        foreach ($entries as $entry) {
            $lines[] = $this->writer->entry($entry);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<IndexEntry>  $entries
     * @return array<string, string> path => agent Markdown, for pages the caller may see
     */
    private function pages(DocSet $set, string $version, string $locale, array $entries): array
    {
        $pages = [];

        foreach ($entries as $entry) {
            $page = $this->docs->page($set, OutlineBuilder::ref($set, $version, $locale, $entry));

            if ($page !== null) {
                $pages[$entry->path] = $this->docs->markdown($set, $page, false);
            }
        }

        return $pages;
    }

    /** @param array<string, string> $pages */
    private function length(array $pages): int
    {
        return array_sum(array_map(fn (string $markdown) => mb_strlen($markdown) + 2, $pages));
    }

    private function localeArgument(DocSet $set, string $locale): ?string
    {
        return $locale === $set->defaultLocale()->code ? null : $locale;
    }
}
