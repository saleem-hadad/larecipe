<?php

namespace Workbench\App\Support;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

/**
 * Two fixed people for working on the admin UI locally, without a users
 * table: a manager the manageLarecipe gate lets in, and a reader it does not.
 */
final class WorkbenchUsers implements UserProvider
{
    public const MANAGER = 'manager@workbench.test';

    public const PEOPLE = [
        'manager' => ['id' => 1, 'name' => 'Ada Manager', 'email' => self::MANAGER],
        'reader' => ['id' => 2, 'name' => 'Sam Reader', 'email' => 'reader@workbench.test'],
    ];

    public static function named(string $who): ?GenericUser
    {
        return isset(self::PEOPLE[$who]) ? new GenericUser(self::PEOPLE[$who] + ['password' => '', 'remember_token' => null]) : null;
    }

    public function retrieveById($identifier): ?Authenticatable
    {
        foreach (array_keys(self::PEOPLE) as $who) {
            if (self::PEOPLE[$who]['id'] === (int) $identifier) {
                return self::named($who);
            }
        }

        return null;
    }

    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return null;
    }

    public function updateRememberToken(Authenticatable $user, $token): void {}

    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        return null;
    }

    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        return false;
    }

    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void {}
}
