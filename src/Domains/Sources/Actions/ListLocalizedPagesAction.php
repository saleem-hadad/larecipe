<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageSummary;

/**
 * The pages a locale serves: every page of the default locale, each replaced by
 * its translation where one exists, plus pages that exist only in the translation.
 *
 * This is the per-locale scope every surface shares (navigation, llms.txt,
 * skill.md, search and MCP), so a reader of the Arabic docs can reach every page
 * and agents see the same list. A page whose ref keeps the default locale is a
 * fallback: it is served in the default language with a notice.
 */
final class ListLocalizedPagesAction
{
    public function __construct(private readonly ListPageSummariesAction $summaries) {}

    /** @return array<string, PageSummary> keyed by path, in the default locale's order */
    public function execute(DocSet $set, string $version, string $locale): array
    {
        $default = $set->defaultLocale()->code;
        $original = $this->summaries->execute($set->key, $version, $default);

        if ($locale === $default) {
            return $original;
        }

        return array_replace($original, $this->summaries->execute($set->key, $version, $locale));
    }
}
