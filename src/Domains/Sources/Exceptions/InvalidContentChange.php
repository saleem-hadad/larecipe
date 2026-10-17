<?php

namespace LaRecipe\Domains\Sources\Exceptions;

use InvalidArgumentException;

/**
 * A change that cannot be applied as asked: a page that is not there, a path
 * that is taken, a navigation patch that points nowhere.
 */
final class InvalidContentChange extends InvalidArgumentException
{
    public static function missingPage(string $path): self
    {
        return new self("There is no page at [{$path}].");
    }

    public static function pathTaken(string $path): self
    {
        return new self("A page already exists at [{$path}].");
    }

    public static function invalidPatch(string $reason): self
    {
        return new self("The navigation patch is invalid: {$reason}");
    }

    public static function invalidAddress(string $address, string $reason): self
    {
        return new self("[{$address}] is not a page address in this doc set: {$reason}");
    }
}
