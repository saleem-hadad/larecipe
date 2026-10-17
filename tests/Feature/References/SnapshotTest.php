<?php

use LaRecipe\Domains\References\Actions\ReferencesFingerprintAction;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\References\Laravel\RouteAllowList;
use LaRecipe\Domains\References\Laravel\RouteResolver;
use LaRecipe\Domains\References\Services\ReferenceRegistry;
use LaRecipe\Domains\References\Services\SnapshotStore;
use LaRecipe\Tests\Support\TrackG;

beforeEach(function () {
    TrackG::bootFixtureApp();

    $this->snapshotDir = sys_get_temp_dir().'/larecipe-references-'.bin2hex(random_bytes(4));
    app()->instance(SnapshotStore::class, new SnapshotStore($this->snapshotDir.'/references.json'));
    app()->forgetInstance(ReferenceRegistry::class);
});

afterEach(function () {
    TrackG::remove($this->snapshotDir);
});

/** CI without a database: the connection points at a file that does not exist. */
function withoutDatabase(): void
{
    config(['database.connections.testing.database' => '/nonexistent/larecipe/database.sqlite']);
    app('db')->purge('testing');
    app()->forgetInstance(ReferenceRegistry::class);
}

it('writes every route, command, allowlisted model and event the app can describe', function () {
    $this->artisan('larecipe:references:snapshot')->assertSuccessful();

    $snapshot = json_decode(file_get_contents($this->snapshotDir.'/references.json'), true);

    expect($snapshot['references']['route'])->toHaveKey('orders.store')
        ->and($snapshot['references']['artisan'])->toHaveKey('orders:prune')
        ->and(array_keys($snapshot['references']['model']))->toBe(['TrackGApp\Models\Customer', 'TrackGApp\Models\Order', 'TrackGApp\Models\OrderItem'])
        ->and($snapshot['references']['event'])->toHaveKey('TrackGApp\Events\OrderShipped')
        ->and($snapshot['references']['model']['TrackGApp\Models\Order']['facts'])->toBe([['Table', 'orders']]);
});

it('falls back to the snapshot when the app cannot answer', function () {
    $this->artisan('larecipe:references:snapshot')->assertSuccessful();

    withoutDatabase();

    $reference = app(ResolveReferenceAction::class)->execute('model', 'Order');

    expect($reference)->not->toBeNull()
        ->and(collect($reference->tables[0]->rows)->pluck(0)->all())->toContain('number', 'total');
});

it('never lets the snapshot hide a reference the live app says is gone', function () {
    $this->artisan('larecipe:references:snapshot')->assertSuccessful();

    app('router')->getRoutes()->getByName('orders.store')->name('orders.create');
    app('router')->getRoutes()->refreshNameLookups();

    expect(app(ResolveReferenceAction::class)->execute('route', 'orders.store'))->toBeNull();
});

it('keeps the previous snapshot of a kind it cannot read now', function () {
    $this->artisan('larecipe:references:snapshot')->assertSuccessful();

    withoutDatabase();

    $this->artisan('larecipe:references:snapshot')->assertSuccessful()->expectsOutputToContain('kept the previous snapshot');

    expect(json_decode(file_get_contents($this->snapshotDir.'/references.json'), true)['references']['model'])
        ->toHaveKey('TrackGApp\Models\Order');
});

it('changes the fingerprint when the surface a page reads changes, and only then', function () {
    $fingerprint = app(ReferencesFingerprintAction::class);
    $before = $fingerprint->execute(['Route']);

    expect($fingerprint->execute(['Route']))->toBe($before)
        ->and($fingerprint->execute(['Route', 'Callout']))->toBe($before);

    app('router')->get('refunds', fn () => 'refunds')->name('refunds.index');

    expect($fingerprint->execute(['Route']))->not->toBe($before)
        ->and($fingerprint->execute(['Artisan']))->not->toBe($fingerprint->execute(['Route']));
});

it('uses the route cache file as the route fingerprint when routes are cached', function () {
    $cache = $this->snapshotDir.'/routes-v7.php';
    mkdir($this->snapshotDir, 0777, true);
    file_put_contents($cache, '<?php return [];');

    $resolver = new RouteResolver(app('router'), app(), new RouteAllowList(null, base_path('vendor')), $cache);
    $first = $resolver->fingerprint();

    file_put_contents($cache, '<?php return ["changed"];');

    expect($first)->toStartWith('cache:')->and($resolver->fingerprint())->not->toBe($first);
});
