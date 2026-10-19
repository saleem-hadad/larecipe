<?php

namespace LaRecipe\Domains\Content\Enums;

enum Visibility: string
{
    case Public = 'public';
    case Private = 'private';

    /**
     * A visibility setting from frontmatter or larecipe.json, failing closed:
     * no value is public, "public" in any case or spacing is public, and any
     * other value ("Private", a typo) is private, so a mistake can never
     * publish what was meant to stay private. Validation reports the value.
     */
    public static function fromSetting(mixed $value): self
    {
        if ($value === null) {
            return self::Public;
        }

        return is_string($value) && strtolower(trim($value)) === self::Public->value ? self::Public : self::Private;
    }
}
