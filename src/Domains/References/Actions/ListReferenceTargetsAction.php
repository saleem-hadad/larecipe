<?php

namespace LaRecipe\Domains\References\Actions;

use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Services\ReferenceRegistry;

/**
 * Everything one kind of live reference could point at: every named route,
 * every command, every allowlisted model, every known event. The server-side
 * docs agent uses it to compare the app's public surface with the docs.
 *
 * It lists names only, read through the same resolvers as the components, so
 * it can never reach configuration or environment values.
 */
final class ListReferenceTargetsAction
{
    public function __construct(private readonly ReferenceRegistry $registry) {}

    /** @return list<string> sorted names, or an empty list for an unknown kind */
    public function execute(string $kind): array
    {
        $resolver = $this->registry->get($kind);

        return $resolver instanceof ListsReferenceTargets ? $resolver->targets() : [];
    }
}
