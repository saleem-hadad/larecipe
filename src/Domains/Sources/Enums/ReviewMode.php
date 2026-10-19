<?php

namespace LaRecipe\Domains\Sources\Enums;

/**
 * How a set treats changes made outside a pull request (config `review`).
 */
enum ReviewMode: string
{
    /** Agent changes made outside a pull request wait as drafts until a person publishes them. */
    case Required = 'required';

    /** They publish at once, still revisioned, attributed and one step from a revert. */
    case Auto = 'auto';

    public static function fromConfig(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Required;
    }
}
