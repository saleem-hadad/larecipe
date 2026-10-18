<?php

/*
 * <Artisan> describes the commands an app lists, with the defaults its
 * signature spells out. Hidden commands stay hidden, and a default the app
 * computes at runtime (often from config) is left out.
 */

use Illuminate\Contracts\Console\Kernel;
use LaRecipe\Domains\References\Actions\ResolveReferenceAction;
use LaRecipe\Domains\References\Services\ReferenceRegistry;
use LaRecipe\Tests\Support\TrackG;
use TrackGApp\Console\Commands\ExportLedgerCommand;
use TrackGApp\Console\Commands\RotateKeysCommand;
use TrackGApp\Console\Commands\SendReportsCommand;
use TrackGApp\Console\Commands\SyncTenantsCommand;

beforeEach(function () {
    TrackG::bootFixtureApp();

    config()->set('services.region', 'eu-internal-7');
    config()->set('services.ledger.disk', 's3-ledger-PRIVATE');

    foreach ([SendReportsCommand::class, SyncTenantsCommand::class, ExportLedgerCommand::class, RotateKeysCommand::class] as $command) {
        app(Kernel::class)->registerCommand(new $command);
    }

    app()->forgetInstance(ReferenceRegistry::class);
});

function defaultsOf(string $command): array
{
    $reference = app(ResolveReferenceAction::class)->execute('artisan', $command);

    return collect($reference->tables)->flatMap(fn ($table) => collect($table->rows)->mapWithKeys(fn ($row) => [$row[0] => $row[2]]))->all();
}

it('shows the defaults written in a command\'s signature', function () {
    expect(defaultsOf('reports:send'))->toBe(['period' => 'weekly', '--queue=QUEUE' => 'reports', '--region=REGION' => '']);
});

it('leaves out defaults the app computes at runtime', function () {
    expect(defaultsOf('tenants:sync'))->toBe(['--region=REGION' => '', '--limit=LIMIT' => '100'])
        ->and(defaultsOf('ledger:export'))->toBe(['--disk=DISK' => ''])
        ->and(defaultsOf('orders:prune'))->toBe(['tenant' => '', '--days=DAYS' => '', '--dry-run' => '']);
});

it('never describes a hidden command', function () {
    expect(app(ResolveReferenceAction::class)->execute('artisan', 'keys:rotate-internal'))->toBeNull()
        ->and(app(ReferenceRegistry::class)->get('artisan')->targets())->not->toContain('keys:rotate-internal')
        ->toContain('reports:send');
});
