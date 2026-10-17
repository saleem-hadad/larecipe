<?php

namespace LaRecipe\Domains\Access\Contracts;

/**
 * Asks the host app whether a reader may do something. The Laravel adapter
 * uses gates and policies; the reader is whatever the host's guard returns.
 */
interface Authorizer
{
    public function user(): ?object;

    public function allows(string $ability, array $arguments = []): bool;

    public function defines(string $ability): bool;

    /** @return list<string> every ability the host defines, for "did you mean" hints */
    public function definedAbilities(): array;
}
