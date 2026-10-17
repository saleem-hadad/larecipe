<?php

namespace LaRecipe\Domains\Admin\Support;

/**
 * The name the admin shows for a person and records on their revisions: the
 * user's name, else their email, else "admin". Hosts' user models differ, so
 * this reads plain attributes and never assumes a class.
 */
final class AuthorName
{
    public static function of(?object $user): string
    {
        foreach (['name', 'email'] as $field) {
            $value = data_get($user, $field);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'admin';
    }
}
