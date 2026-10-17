<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\DTOs\IndexEntry;
use LaRecipe\Domains\Agents\DTOs\Outline;
use LaRecipe\Domains\Agents\DTOs\OutlineSection;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\Enums\VersionStatus;

/**
 * Builds a set's llms.txt files from its navigation, for the caller.
 *
 * - The set root lists the versions (a versioned set) or is the version index.
 * - A version index lists every visible page under its navigation group.
 * - Past the size limit, each top-level group moves to its own path-scoped
 *   file ({version}/{group}/llms.txt) and the index links to it; descriptions
 *   are dropped only as a last resort. Every file stays under the limit, which
 *   is the AFDocs threshold for an index agents can read in one fetch.
 */
final class LlmsTxtBuilder
{
    public const DEFAULT_LIMIT = 50000;

    public function __construct(
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly OutlineBuilder $outlines,
        private readonly SetDescriber $describe,
        private readonly LlmsTxtWriter $writer,
    ) {}

    /** The set's root llms.txt (or a locale's root), or null when the caller can see nothing in it. */
    public function root(DocSet $set, ?string $locale = null, int $limit = self::DEFAULT_LIMIT): ?string
    {
        $locale ??= $set->defaultLocale()->code;

        if (! $set->isVersioned()) {
            return $this->version($set, $set->defaultVersion()->id, $locale, $limit);
        }

        $entries = [];

        foreach ($set->versions() as $version) {
            if (! $this->docs->canViewVersion($set, $version->id)) {
                continue;
            }

            $entries[] = new IndexEntry(
                title: $version->label.($version->default ? ' (default)' : ''),
                url: $this->links->llms($set, $version->id, $this->localeArgument($set, $locale)),
                description: match ($version->status) {
                    VersionStatus::Current => 'Current version.',
                    VersionStatus::Maintained => 'Maintained: fixes only.',
                    VersionStatus::Deprecated => 'Deprecated.',
                    VersionStatus::Archived => 'Archived.',
                },
            );
        }

        if ($entries === []) {
            return null;
        }

        $details = [
            $this->describe->instructions($set),
            'This index lists the versions of these docs. Each version has its own index of pages.',
            ...$this->describe->accessNotes($set, $set->defaultVersion()->id, $locale),
        ];

        return $this->writer->write($this->describe->title($set, null, $locale), $this->describe->summary($set), array_filter($details), [
            new OutlineSection('Versions', null, $entries),
            ...$this->languages($set, null),
        ]);
    }

    /** One version's index, or null when the version is hidden from the caller. */
    public function version(DocSet $set, string $version, string $locale, int $limit = self::DEFAULT_LIMIT): ?string
    {
        if ($set->version($version) === null || ! $this->docs->canViewVersion($set, $version)) {
            return null;
        }

        $outline = $this->outlines->build($set, $version, $locale);
        $title = $this->describe->title($set, $version, $locale);
        $summary = $this->describe->summary($set);
        $details = array_values(array_filter([$this->describe->instructions($set), ...$this->describe->accessNotes($set, $version, $locale)]));
        $languages = $this->languages($set, $version);

        $text = $this->writer->write($title, $summary, $details, [...$outline->sections, ...$this->optional($outline), ...$languages]);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        $split = $this->split($set, $version, $locale, $outline);
        $text = $this->writer->write($title, $summary, $details, [...$split, ...$this->optional($outline), ...$languages]);

        if (mb_strlen($text) <= $limit) {
            return $text;
        }

        return $this->writer->write($title, $summary, $details, array_map(
            fn (OutlineSection $section) => $section->compact(),
            [...$split, ...$this->optional($outline)],
        ));
    }

    /**
     * A path-scoped file for one group ("billing" or "billing/webhooks"), or
     * null when the group does not exist for the caller.
     */
    public function group(DocSet $set, string $version, string $locale, string $slugPath, int $limit = self::DEFAULT_LIMIT): ?string
    {
        if ($set->version($version) === null || ! $this->docs->canViewVersion($set, $version)) {
            return null;
        }

        $section = $this->outlines->build($set, $version, $locale)->section($slugPath);

        if ($section === null) {
            return null;
        }

        $title = $this->describe->title($set, $version, $locale).': '.$section->heading;
        $summary = "The pages in the {$section->heading} section of {$this->describe->title($set, $version, $locale)}.";
        $details = ['The index of every section is at `'.$this->links->llms($set, $version, $this->localeArgument($set, $locale)).'`.'];
        $text = $this->writer->write($title, $summary, $details, [$section]);

        if (mb_strlen($text) <= $limit || $section->children === []) {
            return mb_strlen($text) <= $limit ? $text : $this->writer->write($title, $summary, $details, [$section->compact()]);
        }

        $children = array_map(fn (OutlineSection $child) => new IndexEntry(
            title: $child->heading,
            url: $this->links->llms($set, $version, $this->localeArgument($set, $locale), trim($slugPath, '/').'/'.$child->slug),
            description: count($child->allEntries()).' pages.',
        ), $section->children);

        return $this->writer->write($title, $summary, $details, [
            new OutlineSection($section->heading, $section->slug, $section->entries),
            new OutlineSection('Sections', null, $children),
        ]);
    }

    /**
     * Group sections replaced by links to their own files. Sections without a
     * slug (pages outside any group) stay inline.
     *
     * @return list<OutlineSection>
     */
    private function split(DocSet $set, string $version, string $locale, Outline $outline): array
    {
        $inline = [];
        $files = [];

        foreach ($outline->sections as $section) {
            if ($section->slug === null) {
                $inline[] = $section;

                continue;
            }

            $files[] = new IndexEntry(
                title: $section->heading,
                url: $this->links->llms($set, $version, $this->localeArgument($set, $locale), $section->slug),
                description: count($section->allEntries()).' pages.',
            );
        }

        return [...$inline, new OutlineSection('Sections', null, $files)];
    }

    /** @return list<OutlineSection> */
    private function optional(Outline $outline): array
    {
        return $outline->optional === [] ? [] : [new OutlineSection('Optional', null, $outline->optional)];
    }

    /**
     * The same index in the set's other languages.
     *
     * @return list<OutlineSection>
     */
    private function languages(DocSet $set, ?string $version): array
    {
        if (! $set->isMultilingual()) {
            return [];
        }

        $entries = array_map(fn ($locale) => new IndexEntry(
            title: $locale->label,
            url: $version === null
                ? $this->links->llms($set, null, $this->localeArgument($set, $locale->code))
                : $this->links->llms($set, $version, $this->localeArgument($set, $locale->code)),
            description: $locale->code,
        ), $set->locales());

        return [new OutlineSection('Languages', null, $entries)];
    }

    private function localeArgument(DocSet $set, string $locale): ?string
    {
        return $locale === $set->defaultLocale()->code ? null : $locale;
    }
}
