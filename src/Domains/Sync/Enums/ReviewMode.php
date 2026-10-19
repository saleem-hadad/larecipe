<?php

namespace LaRecipe\Domains\Sync\Enums;

/**
 * How much the owner wants to see before docs changes ship.
 *
 * In required (the default), the agent shows the user the docs diff and waits
 * for approval before committing it. In auto, the owner opted in to the agent
 * updating the docs and pushing without asking. Either way the pull request is
 * the record and the revert path.
 */
enum ReviewMode: string
{
    case Required = 'required';
    case Auto = 'auto';

    public static function fromConfig(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? strtolower(trim($value)) : '') ?? self::Required;
    }
}
