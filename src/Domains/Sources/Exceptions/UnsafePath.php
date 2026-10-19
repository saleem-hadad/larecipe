<?php

namespace LaRecipe\Domains\Sources\Exceptions;

use InvalidArgumentException;

/**
 * A path that would reach outside the doc set's root: "..", an absolute path,
 * a null byte or a symlink that leaves the folder.
 */
final class UnsafePath extends InvalidArgumentException
{
    public static function for(string $path): self
    {
        return new self("LaRecipe refused the path [{$path}]: paths must stay inside the doc set and may not contain '..', absolute parts or control characters.");
    }
}
