<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\CompileProvenanceAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Laravel\ProvenanceIndexPath;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesWritableRepositories;
use Symfony\Component\Process\Process;

uses(UsesWritableRepositories::class);

beforeEach(function () {
    // Compiled indexes go to a temp folder, never into the shared test app's bootstrap/cache.
    $this->app->instance(ProvenanceIndexPath::class, new ProvenanceIndexPath(Fixtures::temporaryDirectory('provenance')));
    $this->app->forgetScopedInstances();
});

$git = function (string $directory, array $arguments, array $env = []): void {
    $process = new Process(['git', '-c', 'commit.gpgsign=false', '-c', 'user.name=Saleem', '-c', 'user.email=saleem@example.com', ...$arguments], $directory, $env + ['GIT_AUTHOR_DATE' => '2026-10-12T09:30:00Z', 'GIT_COMMITTER_DATE' => '2026-10-12T09:30:00Z']);
    $process->mustRun();
};

it('compiles git provenance and serves it with the page', function () use ($git) {
    if ((new Process(['git', '--version']))->run() !== 0) {
        $this->markTestSkipped('git is not installed.');
    }

    $root = $this->setPath('docs');
    $git($root, ['init', '-q']);
    $git($root, ['add', '.']);
    $git($root, ['commit', '-q', '-m', 'Start the docs']);
    file_put_contents($root.'/installation.md', "\nOne more line.\n", FILE_APPEND);
    $git($root, ['add', 'installation.md']);
    $git($root, ['commit', '-q', '-m', "Document the installer (#412)\n\nCo-Authored-By: Claude <noreply@anthropic.com>"]);

    $report = app(CompileProvenanceAction::class)->execute('docs');
    $page = app(GetPageAction::class)->execute(new PageRef('docs', 'current', 'en', 'installation'));

    expect($report->compiled)->toBeTrue()
        ->and($report->method)->toBe('git')
        ->and($page->provenance->actor)->toBe(Actor::Agent)
        ->and($page->provenance->name)->toBe('Claude')
        ->and($page->provenance->approvedBy)->toBe('Saleem')
        ->and($page->provenance->ref)->toBe('https://github.com/acme/acme/pull/412')
        ->and($page->updatedAt->format('Y-m-d'))->toBe('2026-10-12');

    $this->get('/docs/installation', ['Accept' => 'application/vnd.larecipe.page+json'])
        ->assertJsonPath('page.provenance.name', 'Claude')
        ->assertJsonPath('page.provenance.ref', 'https://github.com/acme/acme/pull/412');
});

it('falls back to file modification times without git history', function () {
    touch($this->setPath('docs').'/installation.md', 1791763200);

    $report = app(CompileProvenanceAction::class)->execute('docs');
    $page = app(GetPageAction::class)->execute(new PageRef('docs', 'current', 'en', 'installation'));

    expect($report->method)->toBe('mtime')
        ->and($page->provenance->actor)->toBe(Actor::Human)
        ->and($page->provenance->name)->toBeNull()
        ->and($page->updatedAt->getTimestamp())->toBe(1791763200);
});

it('compiles and clears indexes with larecipe:provenance', function () {
    $index = app(ProvenanceIndexPath::class)->for('guide');

    $this->artisan('larecipe:provenance', ['--set' => ['guide']])->expectsOutputToContain('files from mtime')->assertSuccessful();
    expect(is_file($index))->toBeTrue();

    $this->artisan('larecipe:provenance', ['--set' => ['guide'], '--clear' => true])->assertSuccessful();
    expect(is_file($index))->toBeFalse();
});
