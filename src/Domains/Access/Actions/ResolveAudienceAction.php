<?php

namespace LaRecipe\Domains\Access\Actions;

use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Access\Services\PageAccess;

/**
 * Evaluates, for the current reader, every ability a page's gate blocks ask
 * about, through the one access policy (PageAccess::allows).
 *
 * Only the abilities on the page are evaluated, so the result is the page's
 * audience variant: readers who hold the same abilities share one cached render.
 */
final class ResolveAudienceAction
{
    public function __construct(private readonly PageAccess $access) {}

    /** @param list<string> $abilities the abilities the page asks about (RenderedPage::$abilities) */
    public function execute(array $abilities): Audience
    {
        $evaluated = [];

        foreach (array_unique($abilities) as $ability) {
            $evaluated[$ability] = $this->access->allows($ability);
        }

        return new Audience($evaluated);
    }
}
