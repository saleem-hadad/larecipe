<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Workbench\App\Console\SeedRunbooksCommand;
use Workbench\App\Support\WorkbenchUsers;

/**
 * The admin UI's development and browser-test setup inside the workbench:
 * a "runbooks" database set on a SQLite file, file sessions, two fixed people
 * (a manager and a reader) with a local-only sign-in route, and the
 * manageLarecipe gate that tells them apart.
 *
 *   vendor/bin/testbench workbench:seed-runbooks
 *   vendor/bin/testbench serve, then open /workbench/sign-in/manager
 */
class AdminWorkbenchServiceProvider extends ServiceProvider
{
    public const SET = 'runbooks';

    public const CONNECTION = 'larecipe_workbench';

    public static function databaseFile(): string
    {
        return dirname(__DIR__, 2).'/database/larecipe.sqlite';
    }

    public function register(): void
    {
        config()->set('database.connections.'.self::CONNECTION, [
            'driver' => 'sqlite',
            'database' => self::databaseFile(),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        config()->set('filesystems.disks.larecipe-workbench', [
            'driver' => 'local',
            'root' => dirname(__DIR__, 2).'/storage/larecipe',
        ]);

        config()->set('larecipe.sets.'.self::SET, [
            'route' => ['prefix' => self::SET, 'middleware' => ['web']],
            'source' => ['driver' => 'database', 'connection' => self::CONNECTION, 'cache' => 'file', 'disk' => 'larecipe-workbench'],
            'access' => ['mode' => 'public'],
            'cache' => ['store' => 'file'],
        ]);

        // The admin signs people in, so sessions must outlive a request.
        config()->set('session.driver', 'file');
        // Cache warming after a write runs at once; the workbench has no queue worker.
        config()->set('queue.default', 'sync');
        config()->set('auth.providers.users', ['driver' => 'workbench']);
    }

    public function boot(): void
    {
        Auth::provider('workbench', fn () => new WorkbenchUsers);
        Gate::define('manageLarecipe', fn ($user, string $set) => $user->email === WorkbenchUsers::MANAGER);

        if ($this->app->runningInConsole()) {
            $this->commands([SeedRunbooksCommand::class]);
        }

        if (! $this->app->environment('local', 'testing')) {
            return;
        }

        Route::middleware('web')->group(function () {
            Route::get('/workbench/sign-in/{who}', function (string $who) {
                $user = WorkbenchUsers::named($who) ?? abort(404);
                Auth::guard('web')->login($user);
                request()->session()->regenerate();

                return redirect(request()->query('to', '/'.self::SET.'/_admin'));
            });

            Route::get('/workbench/sign-out', function () {
                Auth::guard('web')->logout();

                return redirect('/'.self::SET);
            });
        });
    }
}
