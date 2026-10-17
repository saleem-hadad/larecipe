<?php

namespace LaRecipe\Domains\Content\Contracts;

use LaRecipe\Domains\Content\DTOs\Manifest;

/**
 * Loads a doc set's larecipe.json. Implemented by the Sources domain, so Content
 * never depends on where content is stored.
 */
interface ManifestProvider
{
    public function manifestFor(string $set): ?Manifest;
}
