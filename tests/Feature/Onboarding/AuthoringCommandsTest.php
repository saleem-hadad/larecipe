<?php

use LaRecipe\Tests\Feature\Sync\TemporaryProject;

beforeEach(function () {
    $this->project = TemporaryProject::create('app');
    $this->project->mount($this->app);
});

afterEach(fn () => $this->project->destroy());

it('creates a page with title, description and sources', function () {
    $this->artisan('larecipe:make:page', [
        'path' => 'billing/refunds',
        '--title' => 'Refunds',
        '--description' => 'Refund an invoice in part or in full.',
        '--source' => ['app/Billing/**', 'routes/api.php'],
    ])->assertSuccessful();

    expect($this->project->read('docs/billing/refunds.md'))->toBe(implode("\n", [
        '---',
        'title: Refunds',
        "description: 'Refund an invoice in part or in full.'",
        'sources: ["app/Billing/**", "routes/api.php"]',
        '---',
        '',
        '# Refunds',
        '',
    ]));

    $this->artisan('larecipe:make:page', ['path' => 'billing/refunds'])->assertFailed();
});

it('puts a translated page in its locale folder', function () {
    $this->artisan('larecipe:make:page', ['path' => 'refunds', '--locale' => 'ar', '--title' => 'المبالغ المستردة'])->assertSuccessful();

    expect($this->project->read('docs/ar/refunds.md'))->toContain("title: 'المبالغ المستردة'");
});

it('freezes the derived navigation once', function () {
    $this->artisan('larecipe:navigation:freeze')->assertSuccessful();

    $navigation = json_decode((string) $this->project->read('docs/navigation.json'), true);

    expect($navigation['$schema'])->toBe('https://larecipe.saleem.dev/schemas/3.x/navigation.json')
        ->and($navigation['groups'])->toBe(['index', 'billing', ['group' => 'Guides', 'pages' => ['guides/webhooks']]]);

    $this->artisan('larecipe:navigation:freeze')->expectsOutputToContain('already frozen')->assertSuccessful();
});

it('versions an unversioned set by moving its pages into a version folder', function () {
    $this->artisan('larecipe:make:version', ['version' => '2.x'])
        ->expectsOutputToContain('--current-as')
        ->assertExitCode(2);

    $this->artisan('larecipe:make:version', ['version' => '2.x', '--current-as' => '1.x', '--default' => true])->assertSuccessful();

    $manifest = json_decode((string) $this->project->read('docs/larecipe.json'), true);

    expect($manifest['versions'])->toBe([['id' => '1.x'], ['id' => '2.x', 'default' => true]])
        ->and($manifest['name'])->toBe('Acme')
        ->and($this->project->read('docs/billing.md'))->toBeNull()
        ->and($this->project->read('docs/1.x/billing.md'))->toContain('title: Billing')
        ->and($this->project->read('docs/2.x/billing.md'))->toContain('title: Billing')
        ->and($this->project->read('docs/2.x/ar/billing.md'))->not->toBeNull()
        ->and($this->project->read('docs/2.x/larecipe.json'))->toBeNull();

    $this->app->forgetScopedInstances();
    $this->artisan('larecipe:make:version', ['version' => '3.x', '--from' => '2.x'])->assertSuccessful();

    expect($this->project->read('docs/3.x/guides/webhooks.md'))->not->toBeNull();
});

it('adds a version to a manifest that lists versions as plain strings', function () {
    $this->artisan('larecipe:make:version', ['version' => '1.x', '--current-as' => '1.x-old'])->assertSuccessful();
    $manifest = json_decode((string) $this->project->read('docs/larecipe.json'), true);
    $manifest['versions'] = ['1.x-old', '1.x'];
    $this->project->write('docs/larecipe.json', json_encode($manifest));
    $this->app->forgetScopedInstances();

    // An existing version is skipped, never added twice.
    $this->artisan('larecipe:make:version', ['version' => '1.x'])->assertSuccessful();
    expect(json_decode((string) $this->project->read('docs/larecipe.json'), true)['versions'])->toBe(['1.x-old', '1.x']);

    $this->artisan('larecipe:make:version', ['version' => '2.x'])->assertSuccessful();

    expect(json_decode((string) $this->project->read('docs/larecipe.json'), true)['versions'])->toBe(['1.x-old', '1.x', '2.x'])
        // Without --from, the copy starts from the default version: the first one listed.
        ->and($this->project->read('docs/2.x/billing.md'))->toContain('title: Billing');

    $this->artisan('larecipe:make:version', ['version' => '3.x', '--from' => '2.x', '--default' => true])->assertSuccessful();

    expect(json_decode((string) $this->project->read('docs/larecipe.json'), true)['versions'])
        ->toBe(['1.x-old', '1.x', '2.x', ['id' => '3.x', 'default' => true]]);
});

it('writes nothing on a dry run', function () {
    $this->artisan('larecipe:make:version', ['version' => '2.x', '--current-as' => '1.x', '--dry-run' => true])->assertSuccessful();
    $this->artisan('larecipe:make:page', ['path' => 'faq', '--dry-run' => true])->assertSuccessful();

    expect($this->project->read('docs/billing.md'))->not->toBeNull()
        ->and($this->project->read('docs/1.x/billing.md'))->toBeNull()
        ->and($this->project->read('docs/faq.md'))->toBeNull();
});
