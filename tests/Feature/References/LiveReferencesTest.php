<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Tests\Support\TrackG;

beforeEach(function () {
    TrackG::bootFixtureApp();
    TrackG::useSets(['shop' => TrackG::set('references', 'shop')]);
});

it('renders a route with its method, URI, middleware and the names of its form request rules', function () {
    $html = $this->get('/shop')->assertOk()->getContent();

    expect($html)
        ->toContain('data-kind="route"')
        ->toContain('<code>POST /orders</code>')
        ->toContain('<code>web, auth</code>')
        ->toContain('Validation rules (StoreOrderRequest)')
        ->toContain('required, email')
        ->toContain('required, integer, min')
        ->toContain('required, in')
        ->not->toContain('usd');
});

it('renders a command with its usage, arguments and options', function () {
    $reference = app(ResolveReferenceAction::class)->execute('artisan', 'orders:prune');

    expect($reference->description)->toBe('Prune old orders')
        ->and($reference->usage)->toStartWith('php artisan orders:prune')->toContain('<tenant>')
        ->and($reference->tables[0]->rows)->toBe([['tenant', 'The tenant to prune (required)', '']])
        ->and($reference->tables[1]->rows[0])->toBe(['--days=DAYS', 'Keep orders newer than this many days', '30'])
        ->and($reference->tables[1]->rows[1])->toBe(['--dry-run', 'Only report what would be pruned', '']);
});

it('renders a model with its table, columns, casts, fillable and relations', function () {
    $reference = app(ResolveReferenceAction::class)->execute('model', 'Order');
    $columns = collect($reference->tables[0]->rows)->keyBy(0);

    expect($reference->title)->toBe('TrackGApp\Models\Order')
        ->and($reference->facts)->toBe([['Table', 'orders']])
        ->and($columns['total'])->toBe(['total', 'integer', 'no', 'integer', 'yes'])
        ->and($columns['shipped_at'][3])->toBe('datetime')
        ->and($columns['internal_note'][4])->toBe('no')
        ->and($reference->tables[1]->rows)->toEqualCanonicalizing([
            ['items', 'HasMany', 'TrackGApp\Models\OrderItem'],
            ['customer', 'BelongsTo', 'TrackGApp\Models\Customer'],
        ]);
});

it('renders an event with its payload and listeners', function () {
    $reference = app(ResolveReferenceAction::class)->execute('event', 'OrderShipped');

    expect($reference->tables[0]->rows)->toBe([
        ['order', 'TrackGApp\Models\Order'],
        ['trackingNumber', 'string'],
        ['carrier', '?string'],
    ])->and($reference->tables[1]->rows)->toBe([
        ['TrackGApp\Listeners\SendShipmentEmail', 'queued'],
        ['TrackGApp\Listeners\UpdateWarehouse', 'sync'],
    ]);
});

it('gives agents the same facts as portable Markdown', function () {
    $registry = app(ComponentRegistry::class);
    $markdown = fn (string $component, array $props) => ($registry->get($component)->markdown)($props, '');

    expect($markdown('Route', ['name' => 'orders.store']))
        ->toContain("| Method | URI | Middleware |\n| --- | --- | --- |\n| `POST` | `/orders` | `web, auth` |")
        ->toContain("Validation rules (StoreOrderRequest):\n\n| Field | Rules |");

    expect($markdown('Artisan', ['command' => 'orders:prune']))
        ->toContain("```bash\nphp artisan orders:prune");

    expect($markdown('Model', ['name' => 'Order']))->toContain('| Column | Type | Nullable | Cast | Fillable |');
    expect($markdown('Event', ['name' => 'OrderShipped']))->toContain('| `trackingNumber` | string |');
});

it('marks the page as reading the app', function () {
    $page = app(GetPageAction::class)->execute(new PageRef('shop', 'current', 'en', 'index'));

    expect(app(RenderPageAction::class)->execute($page)->hasLiveReferences)->toBeTrue();
});

it('renders a clear notice and reports references.missing with the closest name', function () {
    $page = app(GetPageAction::class)->execute(new PageRef('shop', 'current', 'en', 'broken'));
    $rendered = app(RenderPageAction::class)->execute($page);
    $issues = collect($rendered->issues)->keyBy('target');

    expect($rendered->html)
        ->toContain('The route <code>orders.stor</code> is not defined in this app.')
        ->toContain('The command <code>orders:purge</code> is not registered in this app.')
        ->and($issues['orders.stor'])->toMatchArray(['rule' => 'references.missing', 'level' => 'error', 'fix' => 'Use name="orders.store".'])
        ->and($issues['orders:purge']['fix'])->toBe('Use command="orders:prune".')
        ->and($issues)->toHaveKeys(['Invoice', 'TrackGApp\Http\Controllers\OrderController', 'TrackGApp\Http\Requests\StoreOrderRequest']);

    $this->get('/shop/broken')->assertOk()->assertSee('data-missing="true"', false);
});

it('never reads a model outside the allowlist, or an event that is not one', function () {
    $resolve = app(ResolveReferenceAction::class);

    expect($resolve->execute('model', 'TrackGApp\Http\Controllers\OrderController'))->toBeNull()
        ->and($resolve->execute('model', 'Illuminate\Foundation\Auth\User'))->toBeNull()
        ->and($resolve->execute('event', 'TrackGApp\Http\Requests\StoreOrderRequest'))->toBeNull()
        ->and($resolve->execute('model', 'TrackGApp\Models\Order'))->not->toBeNull();
});

it('never exposes configuration or environment values', function () {
    config(['services.stripe.secret' => 'sk_live_CONFIG_SECRET', 'database.connections.testing.password' => 'DB_PASSWORD_SECRET']);
    putenv('LARECIPE_TEST_SECRET=ENV_SECRET');

    $html = $this->get('/shop')->assertOk()->getContent();

    expect($html)->not->toContain('CONFIG_SECRET')->not->toContain('DB_PASSWORD_SECRET')->not->toContain('ENV_SECRET')
        ->and(app(ComponentRegistry::class)->has('Config'))->toBeFalse()
        ->and(app(ComponentRegistry::class)->has('Env'))->toBeFalse();

    putenv('LARECIPE_TEST_SECRET');
});

it('re-renders a cached page when the app surface it reads changes', function () {
    $this->get('/shop/routes')->assertOk()->assertSee('<code>GET /orders/{order}</code>', false);
    $this->get('/shop/routes')->assertOk()->assertSee('<code>GET /orders/{order}</code>', false);

    // A rename in routes/web.php: same route name, new URI.
    app('router')->getRoutes()->getByName('orders.show')->setUri('purchases/{purchase}');

    $this->get('/shop/routes')->assertOk()->assertSee('<code>GET /purchases/{purchase}</code>', false);
});
