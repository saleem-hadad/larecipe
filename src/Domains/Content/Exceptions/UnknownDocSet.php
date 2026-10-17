<?php

namespace LaRecipe\Domains\Content\Exceptions;

use InvalidArgumentException;

final class UnknownDocSet extends InvalidArgumentException
{
    /** @param list<string> $known */
    public static function named(string $key, array $known): self
    {
        return new self(sprintf('LaRecipe has no doc set named [%s]. Known sets: %s. Add it under "sets" in config/larecipe.php.', $key, implode(', ', $known) ?: 'none'));
    }
}
