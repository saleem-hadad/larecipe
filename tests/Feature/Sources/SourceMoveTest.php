<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\ExportSourceAction;
use LaRecipe\Domains\Sources\Actions\ImportBundleAction;
use LaRecipe\Domains\Sources\Actions\MoveSourceAction;
use LaRecipe\Domains\Sources\DTOs\SourceMoveRequest;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Sources\Services\SourceComparer;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesBothDrivers;

uses(UsesBothDrivers::class);

$files = function (string $root): array {
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = substr($file->getPathname(), strlen($root) + 1);
    }

    sort($files);

    return $files;
};

$move = fn (string $from, string $to, array $fromOptions = [], array $toOptions = [], bool $dryRun = false) => app(MoveSourceAction::class)->execute(
    new SourceMoveRequest('guide', $from, $to, $fromOptions, $toOptions, $dryRun, verify: true),
);

it('moves a repository set to the database and back with identical content hashes', function () use ($move, $files) {
    $original = $this->setPath('guide');
    $back = Fixtures::temporaryDirectory('round-trip');

    $toDatabase = $move('repository', 'database', config('larecipe.sets.guide.source'));
    $toRepository = $move('database', 'repository', [], ['path' => $back]);

    $sources = app(SourceManager::class);

    expect($toDatabase->written)->toBeTrue()
        ->and($toDatabase->mismatches)->toBe([])
        ->and($toDatabase->plan->pages)->toHaveCount(6)
        ->and($toDatabase->plan->navigations)->toBe(1)
        ->and($toDatabase->plan->assets)->toBe(3)
        ->and($toDatabase->plan->warnings[0])->toContain('Revision history is not moved')
        ->and($toRepository->mismatches)->toBe([])
        ->and(app(SourceComparer::class)->compare($sources->make('guide', 'repository', ['path' => $original]), $sources->make('guide', 'repository', ['path' => $back])))->toBe([])
        ->and($files($back))->toBe($files($original))
        ->and(json_decode(file_get_contents($back.'/2.x/navigation.json'), true))->toBe(json_decode(file_get_contents($original.'/2.x/navigation.json'), true))
        ->and(json_decode(file_get_contents($back.'/larecipe.json'), true))->toBe(json_decode(file_get_contents($original.'/larecipe.json'), true))
        ->and(file_get_contents($back.'/2.x/_assets/flow.png'))->toBe(file_get_contents($original.'/2.x/_assets/flow.png'));
});

it('serves the set from the database once config points at it', function () use ($move) {
    $move('repository', 'database', config('larecipe.sets.guide.source'));

    config()->set('larecipe.sets.guide.source', ['driver' => 'database']);
    app()->forgetScopedInstances();

    $this->get('/guide/2.x/billing/exports')->assertOk()->assertSee('Download invoices as CSV.');
    $this->get('/guide/ar/2.x')->assertOk()->assertSee('تدير Acme الاشتراكات');
    $this->get('/guide/_assets/current/logo.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
});

it('writes nothing on a dry run, and nothing when the content does not validate', function () use ($move) {
    $plan = $move('repository', 'database', config('larecipe.sets.guide.source'), dryRun: true);

    expect($plan->written)->toBeFalse()
        ->and(iterator_to_array(app(SourceManager::class)->make('guide', 'database')->pages('2.x', 'en')))->toBe([]);

    file_put_contents($this->setPath('guide').'/2.x/empty.md', "---\ntitle: Empty\n---\n");
    $refused = $move('repository', 'database', config('larecipe.sets.guide.source'));

    expect($refused->written)->toBeFalse()
        ->and($refused->validation->errors()[0]->rule)->toBe('page.empty');
});

it('exports to a .tar.gz and imports it into a database set, proven by content hashes', function () {
    $archive = Fixtures::temporaryDirectory('export').'/guide.tar.gz';
    $export = app(ExportSourceAction::class)->execute('guide', $archive);

    config()->set('larecipe.sets.guide.source', ['driver' => 'database']);
    app()->forgetScopedInstances();

    $import = app(ImportBundleAction::class)->execute('guide', $archive, verify: true);

    expect(is_file($archive))->toBeTrue()
        ->and($export->plan->pages)->toHaveCount(6)
        ->and($import->written)->toBeTrue()
        ->and($import->mismatches)->toBe([])
        ->and(app(SourceManager::class)->for('guide')->page(new PageRef('guide', '2.x', 'en', 'installation'))->frontmatter->get('ratio'))->toBe(1.5);
});

it('plans, moves and prints the config change with larecipe:source:move', function () {
    $this->artisan('larecipe:source:move', ['from' => 'repository', 'to' => 'database', '--set' => 'guide', '--dry-run' => true])
        ->expectsOutputToContain('Revision history is not moved')
        ->expectsOutputToContain('Dry run: nothing was written.')
        ->assertSuccessful();

    $this->artisan('larecipe:source:move', ['from' => 'repository', 'to' => 'database', '--set' => 'guide', '--verify' => true])
        ->expectsOutputToContain('Verified: every page')
        ->expectsOutputToContain("'source' => ['driver' => 'database'],")
        ->assertSuccessful();

    expect(file_exists(config('larecipe.sets.guide.source.path').'/larecipe.json'))->toBeTrue();
});

it('exports and imports with the commands, and pulls a bundle into a database set', function () {
    $directory = Fixtures::temporaryDirectory('bundle').'/docs-bundle';

    $this->artisan('larecipe:source:export', ['--set' => 'docs', '--to' => $directory])->assertSuccessful();
    $this->artisan('larecipe:pull', ['--set' => 'kb', '--from' => 'https://acme.dev/kb/mcp'])->expectsOutputToContain('Pass --from with a bundle path')->assertFailed();
    $this->artisan('larecipe:pull', ['--set' => 'kb', '--from' => $directory])->expectsOutputToContain('now matches')->assertSuccessful();
    $this->artisan('larecipe:source:import', ['bundle' => $directory.'/missing', '--set' => 'kb'])->expectsOutputToContain('There is no bundle')->assertFailed();

    expect(is_file($directory.'/installation.md'))->toBeTrue()
        ->and(app(SourceManager::class)->for('kb')->page(new PageRef('kb', 'current', 'en', 'installation'))?->title)->toBe('Installation');
});
