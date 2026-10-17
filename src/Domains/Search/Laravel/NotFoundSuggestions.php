<?php

namespace LaRecipe\Domains\Search\Laravel;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Delivery\DTOs\DocsRequest;
use LaRecipe\Domains\Search\Contracts\SearchEngine;

/**
 * Up to five pages similar to a URL that matched nothing, ranked by the
 * server search. The last path segment names the page, so it is searched
 * first ("billing/webhoks" finds Webhooks before the Billing overview); the
 * whole path's words fill the remaining slots. Only pages the caller may see
 * are suggested, so a 404 never hints that a private page exists. Markdown
 * 404s link the `.md` twins.
 */
final class NotFoundSuggestions
{
    public const LIMIT = 5;

    public function __construct(
        private readonly Reader $reader,
        private readonly SearchEngine $search,
        private readonly SearchUrls $urls,
    ) {}

    /** @return list<array{title: string, url: string, description: ?string}> */
    public function __invoke(DocSet $set, DocsRequest $docs): array
    {
        $version = $set->version($docs->version) !== null ? $docs->version : $set->defaultVersion()->id;
        $locale = $set->locale($docs->locale) !== null ? $docs->locale : $set->defaultLocale()->code;

        if ($docs->path === 'index' || ! $this->reader->canSearch($set, $version)) {
            return [];
        }

        $suggestions = [];

        foreach (array_unique([$this->words(basename($docs->path)), $this->words($docs->path)]) as $query) {
            if ($query === '') {
                continue;
            }

            foreach ($this->search->search($this->reader->query($set, $version, $locale, $query, self::LIMIT * 3)) as $hit) {
                if (count($suggestions) === self::LIMIT) {
                    break 2;
                }

                $suggestions[$hit->ref->path] ??= [
                    'title' => $hit->title,
                    'url' => $this->urls->absolute($hit->ref, $set, markdown: $docs->isMarkdown()),
                    'description' => $hit->description,
                ];
            }
        }

        return array_values($suggestions);
    }

    private function words(string $path): string
    {
        return trim(str_replace(['/', '-', '_', '.'], ' ', $path));
    }
}
