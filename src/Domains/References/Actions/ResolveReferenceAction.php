<?php

namespace LaRecipe\Domains\References\Actions;

use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\Services\ReferenceRegistry;

/**
 * Resolves one live reference against the running app (or the snapshot when the
 * app cannot answer). Used by the Markdown extension, validation and agent tools.
 */
final class ResolveReferenceAction
{
    public function __construct(private readonly ReferenceRegistry $registry) {}

    public function execute(string $kind, string $target, array $props = []): ?Reference
    {
        $target = trim($target);

        if ($target === '') {
            return null;
        }

        return $this->registry->get($kind)?->resolve($target, $props);
    }
}
