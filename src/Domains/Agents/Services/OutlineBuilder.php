<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\DTOs\IndexEntry;
use LaRecipe\Domains\Agents\DTOs\Outline;
use LaRecipe\Domains\Agents\DTOs\OutlineSection;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\PageSummary;

/**
 * Turns the navigation a reader sees into an outline for agents.
 *
 * Pages outside any group form a section named after their tab ("Docs" when
 * the tab has no label). Each group becomes a section with a URL-safe slug,
 * nested groups become child sections. Visible pages the navigation does not
 * list (and that are not hidden) are kept as optional entries, so the index
 * covers every page the sitemap lists.
 */
final class OutlineBuilder
{
    public function __construct(
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
    ) {}

    public function build(DocSet $set, string $version, string $locale): Outline
    {
        $pages = $this->docs->pages($set, $version, $locale);
        $tabs = array_values(array_filter($this->docs->navigation($set, $version, $locale), fn (array $tab) => $tab['href'] === null));
        $labelled = count(array_filter($tabs, fn (array $tab) => $tab['label'] !== null)) > 1;

        $sections = [];
        $listed = [];
        $used = $this->reservedSlugs($set);

        foreach ($tabs as $tab) {
            $prefix = $labelled && $tab['label'] !== null ? $tab['label'].' / ' : '';
            $loose = [];
            $looseAt = null;

            foreach ($tab['items'] as $item) {
                if ($item['type'] === 'page' && isset($pages[$item['path']])) {
                    $looseAt ??= count($sections);
                    $loose[] = $this->entry($set, $pages[$item['path']], $item['title'] ?? null);
                    $listed[$item['path']] = true;
                } elseif ($item['type'] === 'group') {
                    $section = $this->group($set, $pages, $item, $prefix, $listed, $used);

                    if ($section !== null) {
                        $sections[] = $section;
                    }
                }
            }

            if ($loose !== []) {
                array_splice($sections, (int) $looseAt, 0, [new OutlineSection($tab['label'] ?? 'Docs', null, $loose)]);
            }
        }

        $optional = [];

        foreach ($pages as $path => $summary) {
            if (! isset($listed[$path]) && ! $summary->hidden) {
                $optional[] = $this->entry($set, $summary);
            }
        }

        return new Outline($sections, $optional);
    }

    /**
     * @param  array<string, PageSummary>  $pages
     * @param  array<string, true>  $listed
     * @param  array<string, true>  $used
     */
    private function group(DocSet $set, array $pages, array $group, string $prefix, array &$listed, array &$used): ?OutlineSection
    {
        $entries = [];
        $children = [];
        $childSlugs = [];

        foreach ($group['items'] as $item) {
            if ($item['type'] === 'page' && isset($pages[$item['path']])) {
                $entries[] = $this->entry($set, $pages[$item['path']], $item['title'] ?? null);
                $listed[$item['path']] = true;
            } elseif ($item['type'] === 'group') {
                $child = $this->group($set, $pages, $item, '', $listed, $childSlugs);

                if ($child !== null) {
                    $children[] = $child;
                }
            }
        }

        if ($entries === [] && $children === []) {
            return null;
        }

        return new OutlineSection($prefix.$group['label'], $this->slug((string) $group['label'], $used), $entries, $children);
    }

    private function entry(DocSet $set, PageSummary $summary, ?string $title = null): IndexEntry
    {
        return new IndexEntry(
            title: $title ?? $summary->title,
            url: $this->links->page($set, $summary->ref, true),
            description: $summary->description,
            path: $summary->ref->path,
        );
    }

    /** @param array<string, true> $used */
    private function slug(string $label, array &$used): string
    {
        $slug = trim(strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $label)), '-');
        $slug = $slug === '' ? 'section' : $slug;
        $candidate = $slug;

        for ($i = 2; isset($used[$candidate]); $i++) {
            $candidate = $slug.'-'.$i;
        }

        $used[$candidate] = true;

        return $candidate;
    }

    /**
     * Slugs that would read as something else in a URL: locale codes and
     * version ids sit in the same position as a group slug.
     *
     * @return array<string, true>
     */
    private function reservedSlugs(DocSet $set): array
    {
        $reserved = ['llms' => true, 'llms-full' => true, 'skills' => true, 'schemas' => true];

        foreach ($set->locales() as $locale) {
            $reserved[strtolower($locale->code)] = true;
        }

        foreach ($set->versions() as $version) {
            $reserved[strtolower($version->id)] = true;
        }

        return $reserved;
    }

    /** The page a section entry points at, for builders that need the page itself. */
    public static function ref(DocSet $set, string $version, string $locale, IndexEntry $entry): PageRef
    {
        return new PageRef($set->key, $version, $locale, (string) $entry->path);
    }
}
