<?php

use Illuminate\Support\Facades\Artisan;
use LaRecipe\Domains\Sync\Contracts\Codebase;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

beforeEach(function () {
    $this->project = TemporaryProject::create('app');
    $this->project->mount($this->app);

    // The fixture app is not autoloaded, so answer the way the real app would.
    $this->app->bind(Codebase::class, fn () => new class implements Codebase
    {
        public function classFile(string $class): ?string
        {
            return ['App\\Billing\\Invoice' => 'app/Billing/Invoice.php'][$class] ?? null;
        }

        public function commandFile(string $command): ?string
        {
            return ['billing:sync' => 'app/Console/Commands/SyncBilling.php'][$command] ?? null;
        }

        public function routeFile(string $name): ?string
        {
            return null;
        }
    });
});

afterEach(fn () => $this->project->destroy());

it('suggests sources from the classes and commands a page mentions', function () {
    Artisan::call('larecipe:sources:suggest', ['--json' => true]);
    $suggestions = json_decode(Artisan::output(), true);

    expect($suggestions)->toBe([
        ['page' => 'index', 'version' => 'current', 'file' => 'docs/index.md', 'source' => 'app/Billing/Invoice.php', 'kind' => 'class', 'evidence' => ['`App\\Billing\\Invoice`']],
        ['page' => 'index', 'version' => 'current', 'file' => 'docs/index.md', 'source' => 'app/Console/Commands/SyncBilling.php', 'kind' => 'command', 'evidence' => ['`php artisan billing:sync`']],
    ]);
});

it('adds the suggestions to pages without sources, keeping their frontmatter', function () {
    $this->artisan('larecipe:sources:suggest', ['--apply' => true])->assertSuccessful();

    expect($this->project->read('docs/index.md'))->toStartWith(implode("\n", [
        '---',
        'title: Introduction',
        'description: What Acme does and where to start.',
        'sources: ["app/Billing/Invoice.php","app/Console/Commands/SyncBilling.php"]',
        '---',
    ]))
        ->and($this->project->read('docs/billing.md'))->toContain('sources: ["app/Billing/**"]');
});
