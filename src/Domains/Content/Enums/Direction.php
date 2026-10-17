<?php

namespace LaRecipe\Domains\Content\Enums;

enum Direction: string
{
    case Ltr = 'ltr';
    case Rtl = 'rtl';

    /**
     * Languages written right to left, so a manifest that only says "ar" still gets RTL.
     */
    public static function forLocale(string $code): self
    {
        $language = strtolower(explode('-', str_replace('_', '-', $code))[0]);

        return in_array($language, ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'yi', 'dv', 'ckb'], true)
            ? self::Rtl
            : self::Ltr;
    }
}
