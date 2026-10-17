<?php

namespace LaRecipe\Domains\Search\Laravel;

use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageSummary;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Search\Actions\ResolveAudienceAction;
use LaRecipe\Domains\Search\DTOs\Audience;
use LaRecipe\Domains\Search\DTOs\SearchQuery;

/**
 * The reader behind a search request, seen through the one access policy:
 * which pages they may see and which gate abilities they hold. Every search
 * surface (client index, server endpoint, 404 suggestions) starts here, so
 * search follows the same rules as the pages themselves.
 */
final class Reader
{
    public function __construct(
        private readonly PageAccess $access,
        private readonly PageCatalog $catalog,
        private readonly ResolveAudienceAction $audiences,
    ) {}

    public function canSearch(DocSet $set, string $version): bool
    {
        return $this->access->canEnter($set) && $this->access->canViewVersion($set, $version);
    }

    /** @return array<string, PageSummary> */
    public function visible(DocSet $set, string $version, string $locale): array
    {
        return $this->access->visible($set, $this->catalog->summaries($set, $version, $locale));
    }

    public function audience(DocSet $set, string $version, string $locale): Audience
    {
        return $this->audienceOf($set, $version, $locale, $this->visible($set, $version, $locale));
    }

    public function query(DocSet $set, string $version, string $locale, string $query, int $limit): SearchQuery
    {
        $visible = $this->visible($set, $version, $locale);

        return new SearchQuery(
            set: $set->key,
            version: $version,
            locale: $locale,
            query: $query,
            visible: $visible,
            limit: $limit,
            abilities: $this->audienceOf($set, $version, $locale, $visible)->abilities,
        );
    }

    /** Guests on a public set all get the same answers, so shared caches may keep them. */
    public function sharesPublicAnswers(DocSet $set): bool
    {
        return $this->access->isPublic($set) && $this->access->user() === null;
    }

    /** @param array<string, PageSummary> $visible */
    private function audienceOf(DocSet $set, string $version, string $locale, array $visible): Audience
    {
        return $this->audiences->execute(
            $set->key,
            $version,
            $locale,
            array_map('strval', array_keys($visible)),
            fn (string $ability) => $this->access->allows($ability),
        );
    }
}
