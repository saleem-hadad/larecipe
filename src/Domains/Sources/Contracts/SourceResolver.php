<?php

namespace LaRecipe\Domains\Sources\Contracts;

/**
 * Hands out the content source bound to a doc set.
 */
interface SourceResolver
{
    public function for(string $set): ContentSource;
}
