<?php

namespace LaRecipe\Tests\Feature\Agents\Support;

use Illuminate\Foundation\Auth\User;
use Illuminate\Routing\RouteCollection;
use LaRecipe\Domains\Delivery\Laravel\RouteRegistrar;
use LaRecipe\Tests\TestCase;

/**
 * Track C test helpers: switch the configured doc sets (and any config they
 * depend on) after the app has booted, re-registering the routes the way
 * LaRecipe does at boot.
 */
final class Sets
{
    public static function fixture(string $path = ''): string
    {
        return TestCase::fixture('track-c'.($path === '' ? '' : '/'.$path));
    }

    /** The default test sets (docs, handbook) plus the track-c billing set. */
    public static function withBilling(array $billing = [], array $config = []): void
    {
        self::use([
            'docs' => [
                'route' => ['prefix' => 'docs', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => TestCase::fixture('docs')],
                'access' => ['mode' => 'public'],
            ],
            'handbook' => [
                'route' => ['prefix' => 'handbook', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => TestCase::fixture('handbook')],
                'access' => ['mode' => 'public'],
            ],
            'billing' => array_replace_recursive([
                'route' => ['prefix' => 'billing', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => self::fixture('billing')],
                'access' => ['mode' => 'public'],
            ], $billing),
        ], $config);
    }

    /** @param array<string, array> $sets */
    public static function use(array $sets, array $config = []): void
    {
        $app = app();
        $app['config']->set('larecipe.sets', $sets);

        foreach ($config as $key => $value) {
            $app['config']->set($key, $value);
        }

        $app->forgetScopedInstances();
        $app['router']->setRoutes(new RouteCollection);
        $app->make(RouteRegistrar::class)->register($app->make('larecipe.set-configs'));
        $app['router']->getRoutes()->refreshNameLookups();
        $app['router']->getRoutes()->refreshActionLookups();
    }

    /** An absolute URL the way the app builds it, so assertions do not depend on the test host. */
    public static function url(string $path): string
    {
        return rtrim(url('/'), '/').'/'.ltrim($path, '/');
    }

    /** Text with the app's own scheme and host removed from URLs, for host-independent assertions. */
    public static function local(string $text): string
    {
        return str_replace(rtrim(url('/'), '/'), '', $text);
    }

    public static function user(int $id = 1): User
    {
        $user = new User;
        $user->forceFill(['id' => $id, 'name' => 'Reader '.$id, 'email' => "reader{$id}@example.com"]);

        return $user;
    }
}
