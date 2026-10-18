<?php

/*
 * <Route> shows the public shape of a route and nothing a host computed:
 * field names and rule names, never rule arguments (they are often built
 * from config), and never the domain. references.routes.allow chooses which
 * routes the docs may describe at all.
 */

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\References\Laravel\RouteAllowList;
use LaRecipe\Domains\References\Laravel\RouteResolver;
use LaRecipe\Domains\References\Services\ReferenceHtml;
use LaRecipe\Domains\References\Services\ReferenceMarkdown;
use LaRecipe\Domains\References\Services\ReferenceRegistry;
use LaRecipe\Domains\References\Services\SnapshotStore;
use LaRecipe\Tests\Support\TrackG;

beforeEach(function () {
    TrackG::bootFixtureApp();

    config()->set('services.webhook.secret', 'whsec_TOPSECRET123');
    config()->set('services.billing.internal_plans', ['legacy-free-forever', 'staff-comp']);
    config()->set('services.region', 'eu-internal-7');

    Route::post('internal/billing/override', ['TrackGApp\Http\Controllers\OrderController', 'override'])
        ->domain('admin.internal.example.test')
        ->middleware(['web', 'can:super-admin', 'throttle:5,1'])
        ->name('internal.billing.override');
    app('router')->getRoutes()->refreshNameLookups();
});

function useRouteAllowList(?array $patterns): void
{
    config()->set('larecipe.references.routes.allow', $patterns);
    app()->forgetInstance(ReferenceRegistry::class);
}

it('prints field and rule names, never rule arguments, the domain or middleware parameters', function () {
    $reference = app(ResolveReferenceAction::class)->execute('route', 'internal.billing.override');
    $text = (new ReferenceHtml)->render($reference).(new ReferenceMarkdown)->render($reference);

    expect($text)->not->toContain('TOPSECRET')
        ->not->toContain('legacy-free-forever')
        ->not->toContain('eu-internal-7')
        ->not->toContain('tenants')
        ->not->toContain('admin.internal.example.test')
        ->not->toContain('super-admin')
        ->and($reference->facts)->toBe([['Method', 'POST'], ['URI', '/internal/billing/override'], ['Middleware', 'web, can, throttle']])
        ->and($reference->tables[0]->rows)->toBe([
            ['plan', 'required, in'],
            ['signature', 'required, in'],
            ['tenant', 'exists'],
            ['notes', 'nullable, string, max, custom'],
        ]);
});

it('describes only the routes references.routes.allow names, by name or URI', function () {
    useRouteAllowList(['orders.*', '/internal/*/status']);

    $resolve = app(ResolveReferenceAction::class);

    expect($resolve->execute('route', 'orders.store'))->not->toBeNull()
        ->and($resolve->execute('route', 'internal.billing.override'))->toBeNull()
        ->and(app(ReferenceRegistry::class)->get('route')->targets())->not->toContain('internal.billing.override');

    useRouteAllowList(['internal/billing/*']);

    expect(app(ResolveReferenceAction::class)->execute('route', 'internal.billing.override'))->not->toBeNull()
        ->and(app(ResolveReferenceAction::class)->execute('route', 'orders.store'))->toBeNull();
});

it('leaves out routes that packages register, except LaRecipe\'s own, when no list is set', function () {
    // Everything in this repository counts as a package here, the way vendor/ does in an app.
    $resolver = new RouteResolver(app('router'), app(), new RouteAllowList(null, dirname(__DIR__, 3)));
    $larecipe = collect(app('router')->getRoutes()->getRoutes())->first(fn ($route) => str_starts_with((string) $route->getName(), 'larecipe.'));

    expect($resolver->resolve('orders.show'))->toBeNull()
        ->and($resolver->targets())->not->toContain('orders.show')
        ->and($resolver->resolve($larecipe->getName()))->not->toBeNull();

    // With vendor/ as the package folder, the fixture app's routes are the app's own, Route::view() included.
    Route::view('pricing', 'welcome')->name('pricing');
    Route::get('telescope/requests', [Controller::class, 'callAction'])->name('telescope.requests');
    app('router')->getRoutes()->refreshNameLookups();
    $app = new RouteResolver(app('router'), app(), new RouteAllowList(null, dirname(__DIR__, 3).'/vendor'));

    expect($app->resolve('orders.show'))->not->toBeNull()
        ->and($app->resolve('orders.store'))->not->toBeNull()
        ->and($app->resolve('pricing'))->not->toBeNull()
        ->and($app->resolve('telescope.requests'))->toBeNull();

    // php artisan route:cache stores closures serialized; they are still the app's own.
    app('router')->getRoutes()->getByName('orders.show')->prepareForSerialization();

    expect($app->resolve('orders.show'))->not->toBeNull();
});

it('snapshots only the routes the list allows', function () {
    $directory = sys_get_temp_dir().'/larecipe-references-'.bin2hex(random_bytes(4));
    app()->instance(SnapshotStore::class, new SnapshotStore($directory.'/references.json'));
    useRouteAllowList(['orders.*']);

    $this->artisan('larecipe:references:snapshot')->assertSuccessful();
    $routes = json_decode(file_get_contents($directory.'/references.json'), true)['references']['route'];

    expect(array_keys($routes))->toBe(['orders.show', 'orders.store'])
        ->and(json_encode($routes))->not->toContain('TOPSECRET');

    TrackG::remove($directory);
});
