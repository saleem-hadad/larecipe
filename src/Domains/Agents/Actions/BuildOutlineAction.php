<?php

namespace LaRecipe\Domains\Agents\Actions;

use LaRecipe\Domains\Agents\DTOs\Outline;
use LaRecipe\Domains\Agents\Services\OutlineBuilder;
use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * One version and locale of a set as agents see it: sections in reading order
 * with titles, descriptions and Markdown URLs, limited to the caller's pages.
 */
final class BuildOutlineAction
{
    public function __construct(private readonly OutlineBuilder $builder) {}

    public function execute(DocSet $set, string $version, string $locale): Outline
    {
        return $this->builder->build($set, $version, $locale);
    }
}
