<?php

namespace LaRecipe\Domains\Search\Actions;

use Closure;
use LaRecipe\Domains\Search\DTOs\Audience;
use LaRecipe\Domains\Search\Services\SearchIndexRepository;

/**
 * Works out which index variant a reader gets: the pages they may see plus the
 * gate abilities they hold among those the pages ask about. Cheap enough for
 * every page view: it reads a small cached summary, never the whole index.
 */
final class ResolveAudienceAction
{
    public function __construct(private readonly SearchIndexRepository $indexes) {}

    /**
     * @param  list<string>  $visiblePaths  pages the reader may see in this version and locale
     * @param  Closure(string $ability): bool  $allows  whether the reader holds an ability
     */
    public function execute(string $set, string $version, string $locale, array $visiblePaths, Closure $allows): Audience
    {
        $granted = array_values(array_filter($this->indexes->abilities($set, $version, $locale), $allows));

        return Audience::for($this->indexes->fingerprint($set, $version, $locale), $visiblePaths, $granted);
    }
}
