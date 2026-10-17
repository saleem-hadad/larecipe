<?php

namespace LaRecipe\Domains\References\Contracts;

/**
 * A resolver that can list everything it could resolve: every named route, every
 * command, every allowlisted model. Used for "did you mean" fixes and snapshots.
 */
interface ListsReferenceTargets
{
    /** @return list<string> */
    public function targets(): array;
}
