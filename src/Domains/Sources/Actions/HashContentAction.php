<?php

namespace LaRecipe\Domains\Sources\Actions;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Sources\Services\ContentHashes;

/**
 * The content hash of a navigation tree or a manifest, exactly as the write
 * Actions compute it, so a writer outside this domain (the admin UI) can send
 * it back as basedOn. Pages carry theirs on the Page DTO. Null means "does not
 * exist" (zero-config navigation, a set without larecipe.json).
 */
final class HashContentAction
{
    public function execute(NavigationTree|Manifest|null $content): string
    {
        return $content instanceof Manifest
            ? ContentHashes::manifest($content)
            : ContentHashes::navigation($content);
    }
}
