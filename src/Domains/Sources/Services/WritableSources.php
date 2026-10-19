<?php

namespace LaRecipe\Domains\Sources\Services;

use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Sources\Contracts\DraftableContentSource;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\Exceptions\ReadOnlySource;

/**
 * Hands the write Actions the source of a set with the capability they need,
 * and explains clearly when the set's driver does not have it.
 */
final class WritableSources
{
    public function __construct(private readonly SourceResolver $sources) {}

    public function for(string $set): ContentSource
    {
        return $this->sources->for($set);
    }

    public function writable(string $set): WritableContentSource
    {
        $source = $this->sources->for($set);

        if (! $source instanceof WritableContentSource) {
            throw ReadOnlySource::forSet($set, get_debug_type($source));
        }

        return $source;
    }

    public function draftable(string $set): DraftableContentSource
    {
        $source = $this->sources->for($set);

        if (! $source instanceof DraftableContentSource) {
            throw ReadOnlySource::withoutRevisions($set);
        }

        return $source;
    }
}
