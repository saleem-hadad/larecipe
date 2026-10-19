<?php

namespace LaRecipe\Domains\Sources\Actions;

use DateTimeImmutable;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\DTOs\TranslationStatus;

/**
 * Lists the translations in one locale that are older than their source page,
 * and, when asked, the source pages that have no translation yet. This is what
 * `larecipe:affected --locale` reports.
 *
 * The precise check is the content hash: a translation that records the source
 * hash it was made from (`translated_from: sha256:...` in its frontmatter) is
 * stale as soon as the source hash differs. Without it, the update times decide
 * (provenance first, then the file time), which needs no extra frontmatter but
 * can be fooled by a checkout that resets file times.
 */
final class FindStaleTranslationsAction
{
    public const FRONTMATTER_KEY = 'translated_from';

    public function __construct(
        private readonly SourceResolver $sources,
        private readonly ListPageSummariesAction $summaries,
    ) {}

    /** @return list<TranslationStatus> */
    public function execute(DocSet $set, string $version, string $locale, bool $includeMissing = false): array
    {
        $default = $set->defaultLocale()->code;

        if ($locale === $default || $set->locale($locale) === null) {
            return [];
        }

        $source = $this->sources->for($set->key);
        $translated = $this->summaries->execute($set->key, $version, $locale);
        $statuses = [];

        foreach ($this->summaries->execute($set->key, $version, $default) as $path => $summary) {
            $original = $source->page($summary->ref);

            if ($original === null) {
                continue;
            }

            if (! isset($translated[$path])) {
                if ($includeMissing) {
                    $statuses[] = new TranslationStatus($original->ref, $original->ref->withLocale($locale), TranslationStatus::MISSING, 'There is no translation of this page yet.', $original->contentHash);
                }

                continue;
            }

            $translation = $source->page($translated[$path]->ref);

            if ($translation !== null && ($status = $this->compare($original, $translation)) !== null) {
                $statuses[] = $status;
            }
        }

        return $statuses;
    }

    private function compare(Page $original, Page $translation): ?TranslationStatus
    {
        $recorded = $translation->frontmatter->get(self::FRONTMATTER_KEY);

        if (is_string($recorded) && $recorded !== '') {
            return $recorded === $original->contentHash ? null : new TranslationStatus(
                $original->ref,
                $translation->ref,
                TranslationStatus::STALE,
                'The source page changed since this translation was made from it.',
                $original->contentHash,
                $recorded,
                $this->updatedAt($original),
                $this->updatedAt($translation),
            );
        }

        $sourceTime = $this->updatedAt($original);
        $translationTime = $this->updatedAt($translation);

        return $sourceTime !== null && $translationTime !== null && $sourceTime > $translationTime ? new TranslationStatus(
            $original->ref,
            $translation->ref,
            TranslationStatus::STALE,
            'The source page was updated after this translation.',
            $original->contentHash,
            null,
            $sourceTime,
            $translationTime,
        ) : null;
    }

    private function updatedAt(Page $page): ?DateTimeImmutable
    {
        return $page->provenance?->at ?? $page->updatedAt;
    }
}
