<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;

/**
 * Every page of one version and locale, without bodies, keyed by path.
 *
 * Navigation, llms.txt, search and list_pages all start here. Callers that serve
 * requests cache the result by the source fingerprint.
 */
final class ListPageSummariesAction
{
    public function __construct(private readonly SourceResolver $sources) {}

    /** @return array<string, PageSummary> */
    public function execute(string $set, string $version, string $locale): array
    {
        $source = $this->sources->for($set);
        $summaries = [];

        foreach ($source->pages($version, $locale) as $ref) {
            $page = $source->page($ref);

            if ($page !== null) {
                $summaries[$ref->path] = PageSummary::fromPage($page);
            }
        }

        return $summaries;
    }
}
