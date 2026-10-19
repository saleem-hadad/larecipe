<?php

use Illuminate\Support\Facades\Event;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Onboarding\Actions\ScaffoldDatabaseDocSetAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\ListConflictsAction;
use LaRecipe\Domains\Sources\Actions\PublishDraftAction;
use LaRecipe\Domains\Sources\Actions\UpdateManifestAction;
use LaRecipe\Domains\Sources\Contracts\DocsMigrationRepository;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\DTOs\ReviewDecision;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Events\DocsMigrationConflicted;
use LaRecipe\Domains\Sources\Exceptions\DocsMigrationFailed;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Sources\Services\ContentHashes;
use LaRecipe\Domains\Sync\Services\LocalWorkspace;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;

uses(UsesDatabaseSets::class);

$page = fn (string $path) => app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', $path));

/** Write a docs migration file; {{ hash }} and friends are replaced from $values. */
$migration = function (string $name, string $code, array $values = []): string {
    $file = config('larecipe.migrations.path')."/{$name}.php";
    file_put_contents($file, strtr($code, $values));

    return $file;
};

$document = <<<'PHP'
<?php

use LaRecipe\Migrations\DocsMigration;

return new class extends DocsMigration
{
    protected string $set = 'kb';

    protected string $author = 'claude-code';

    protected string $ref = 'https://github.com/acme/acme/pull/482';

    protected string $summary = 'Document the --agents flag';

    public function up(): void
    {
        $this->updatePage('installation', basedOn: '{{ hash }}', frontmatter: [
            'title' => 'Installation',
            'description' => 'Add Acme to any Laravel app.',
        ], body: <<<'MD'
            # Installation

            Run the installer and choose which coding agents to connect:

            ```bash
            php artisan acme:install --agents=claude,codex
            ```
            MD);

        $this->createPage('agents/connect', frontmatter: [
            'title' => 'Connect your agents',
            'description' => 'Point your coding agents at the docs.',
        ], body: <<<'MD'
            # Connect your agents
            MD);

        $this->updateNavigation(basedOn: 'absent', patch: [
            ['add' => 'agents/connect', 'group' => 'Agents'],
        ]);
    }
};
PHP;

beforeEach(function () {
    Fixtures::write('kb', 'installation', "# Installation\n\nComposer first.\n", ['title' => 'Installation', 'description' => 'Add Acme to any Laravel app.']);
});

it('applies with php artisan migrate as published revisions with provenance', function () use ($page, $migration, $document) {
    $migration('2026_10_17_141502_docs_document_agents_flag', $document, ['{{ hash }}' => $page('installation')->contentHash]);

    $this->artisan('migrate')->assertSuccessful();
    app()->forgetScopedInstances();

    expect($page('installation')->body)->toContain('--agents=claude,codex')
        ->and($page('installation')->provenance->actor)->toBe(Actor::Migration)
        ->and($page('installation')->provenance->name)->toBe('claude-code')
        ->and($page('installation')->provenance->ref)->toBe('https://github.com/acme/acme/pull/482')
        ->and($page('agents/connect')->title)->toBe('Connect your agents')
        ->and(collect(app(SourceManager::class)->for('kb')->navigation('current', 'en')->toJson()['groups'])->firstWhere('group', 'Agents'))
        ->toBe(['group' => 'Agents', 'pages' => ['agents/connect']])
        ->and(app(DocsMigrationRepository::class)->find('2026_10_17_141502_docs_document_agents_flag')->operations)->toHaveCount(3);
});

it('rolls back with migrate:rollback: restores replaced revisions and removes created pages', function () use ($page, $migration, $document) {
    $migration('2026_10_17_141502_docs_document_agents_flag', $document, ['{{ hash }}' => $page('installation')->contentHash]);
    $this->artisan('migrate')->assertSuccessful();

    $this->artisan('migrate:rollback')->assertSuccessful();
    app()->forgetScopedInstances();

    expect($page('installation')->body)->toBe("# Installation\n\nComposer first.\n")
        ->and($page('agents/connect'))->toBeNull()
        ->and(app(SourceManager::class)->for('kb')->navigation('current', 'en'))->toBeNull()
        ->and(app(DocsMigrationRepository::class)->find('2026_10_17_141502_docs_document_agents_flag'))->toBeNull();
});

it('turns a change written against an old version into a conflict draft and still completes', function () use ($page, $migration, $document) {
    Event::fake([DocsMigrationConflicted::class]);
    $hash = $page('installation')->contentHash;

    // Production moved on after the migration was written (an admin UI edit).
    Fixtures::write('kb', 'installation', "# Installation\n\nEdited in production.\n", ['title' => 'Installation', 'description' => 'Add Acme to any Laravel app.'], Channel::AdminUi, Fixtures::human());

    $migration('2026_10_17_141502_docs_document_agents_flag', $document, ['{{ hash }}' => $hash]);

    $this->artisan('migrate')
        ->expectsOutputToContain('LaRecipe docs conflict: [installation]')
        ->assertSuccessful();
    app()->forgetScopedInstances();

    $conflicts = app(ListConflictsAction::class)->execute('kb');

    expect($page('installation')->body)->toContain('Edited in production.')
        ->and($page('agents/connect'))->not->toBeNull()
        ->and($conflicts)->toHaveCount(1)
        ->and($conflicts[0]->payload['body'])->toContain('--agents=claude,codex')
        ->and($conflicts[0]->conflict['body'])->toContain('Edited in production.')
        ->and($conflicts[0]->basedOnHash)->toBe($hash)
        ->and($conflicts[0]->migration)->toBe('2026_10_17_141502_docs_document_agents_flag');

    Event::assertDispatched(DocsMigrationConflicted::class, fn ($event) => $event->revision?->id === $conflicts[0]->id);
    $this->assertDatabaseHas('migrations', ['migration' => '2026_10_17_141502_docs_document_agents_flag']);
});

it('treats createPage on an existing path as a conflict', function () use ($page, $migration) {
    $migration('2026_10_17_150000_docs_create_twice', <<<'PHP'
        <?php
        return new class extends LaRecipe\Migrations\DocsMigration
        {
            protected string $set = 'kb';

            public function up(): void
            {
                $this->createPage('installation', frontmatter: ['title' => 'Installation', 'description' => 'x'], body: "# Another installation\n");
            }
        };
        PHP);

    $this->artisan('migrate')->expectsOutputToContain('already exists')->assertSuccessful();
    app()->forgetScopedInstances();

    expect($page('installation')->body)->toBe("# Installation\n\nComposer first.\n")
        ->and(app(ListConflictsAction::class)->execute('kb'))->toHaveCount(1);
});

it('does not clobber an edit made after the migration when rolling back', function () use ($page, $migration, $document) {
    $migration('2026_10_17_141502_docs_document_agents_flag', $document, ['{{ hash }}' => $page('installation')->contentHash]);
    $this->artisan('migrate')->assertSuccessful();
    app()->forgetScopedInstances();

    Fixtures::write('kb', 'installation', "# Installation\n\nFixed a typo in production.\n", ['title' => 'Installation', 'description' => 'Add Acme to any Laravel app.'], Channel::AdminUi, Fixtures::human());

    $this->artisan('migrate:rollback')->expectsOutputToContain('changed after')->assertSuccessful();
    app()->forgetScopedInstances();

    $conflicts = app(ListConflictsAction::class)->execute('kb');

    expect($page('installation')->body)->toContain('Fixed a typo in production.')
        ->and($page('agents/connect'))->toBeNull()
        ->and($conflicts)->toHaveCount(1)
        ->and($conflicts[0]->payload['body'])->toBe("# Installation\n\nComposer first.\n");
});

it('fails the run on invalid content, so CI catches it before a deploy', function () use ($migration) {
    $migration('2026_10_17_160000_docs_empty_page', <<<'PHP'
        <?php
        return new class extends LaRecipe\Migrations\DocsMigration
        {
            protected string $set = 'kb';

            public function up(): void
            {
                $this->createPage('empty', frontmatter: ['title' => 'Empty'], body: '');
            }
        };
        PHP);

    $this->withoutExceptionHandling();
    $this->artisan('migrate');
})->throws(DocsMigrationFailed::class, 'The page has no content.');

it('updates the manifest with a merge patch', function () use ($migration) {
    app(UpdateManifestAction::class)->execute(new ManifestDraft('kb', ['name' => 'KB', 'theme' => ['accent' => 'red']], Fixtures::human(), Channel::AdminUi));
    $hash = ContentHashes::manifest(app(SourceManager::class)->for('kb')->manifest());

    $migration('2026_10_17_170000_docs_rename', <<<'PHP'
        <?php
        return new class extends LaRecipe\Migrations\DocsMigration
        {
            protected string $set = 'kb';

            public function up(): void
            {
                $this->updateManifest(basedOn: '{{ hash }}', patch: ['name' => 'Knowledge base', 'theme' => ['radius' => 'soft']]);
            }
        };
        PHP, ['{{ hash }}' => $hash]);

    $this->artisan('migrate')->assertSuccessful();
    app()->forgetScopedInstances();

    expect(app(SourceManager::class)->for('kb')->manifest()->toArray())->toBe(['name' => 'Knowledge base', 'theme' => ['accent' => 'red', 'radius' => 'soft']]);
});

it('skips docs migrations in tests when skip_in_tests is on, and for sets that are not database sets', function () use ($page, $migration, $document) {
    config()->set('larecipe.migrations.skip_in_tests', true);
    config()->set('larecipe.sets.docs.source.path', Fixtures::copy('docs'));
    $migration('2026_10_17_141502_docs_document_agents_flag', $document, ['{{ hash }}' => $page('installation')->contentHash]);
    $migration('2026_10_17_141503_docs_repository_set', str_replace("'kb'", "'docs'", $document), ['{{ hash }}' => 'x']);

    $this->artisan('migrate')->assertSuccessful();
    app()->forgetScopedInstances();

    expect($page('agents/connect'))->toBeNull();

    config()->set('larecipe.migrations.skip_in_tests', false);
    $this->artisan('migrate')->assertSuccessful();

    expect(is_file(config('larecipe.sets.docs.source.path').'/agents/connect.md'))->toBeFalse();
});

it('scaffolds a docs migration with the current content and hash of the pages named', function () use ($page) {
    $this->artisan('larecipe:make:docs-migration', ['name' => 'document billing exports', '--set' => 'kb', '--page' => ['installation']])
        ->assertSuccessful();

    $files = glob(config('larecipe.migrations.path').'/*_docs_document_billing_exports.php');
    $code = file_get_contents($files[0]);

    expect($files)->toHaveCount(1)
        ->and($code)->toContain("\$this->updatePage('installation', basedOn: '{$page('installation')->contentHash}', frontmatter: [")
        ->and($code)->toContain("            Composer first.\n")
        ->and($code)->toContain("protected string \$summary = 'Document billing exports';");

    // The scaffold applies cleanly as written: nothing changed, so nothing to do.
    $this->artisan('migrate')->assertSuccessful();
    expect(app(ListConflictsAction::class)->execute('kb'))->toBe([]);
});

it('resolves a conflict from the review queue by taking the migration', function () use ($page, $migration, $document) {
    $hash = $page('installation')->contentHash;
    Fixtures::write('kb', 'installation', "# Installation\n\nEdited in production.\n", ['title' => 'Installation', 'description' => 'Add Acme to any Laravel app.'], Channel::AdminUi, Fixtures::human());
    $migration('2026_10_17_141502_docs_document_agents_flag', $document, ['{{ hash }}' => $hash]);
    $this->artisan('migrate')->assertSuccessful();
    app()->forgetScopedInstances();

    $conflict = app(ListConflictsAction::class)->execute('kb')[0];
    $taken = app(PublishDraftAction::class)->execute(new ReviewDecision('kb', $conflict->id, Fixtures::human('Reviewer')));

    expect($taken->isPublished())->toBeTrue()
        ->and($page('installation')->body)->toContain('--agents=claude,codex')
        ->and($page('installation')->provenance->approvedBy)->toBe('Reviewer')
        ->and(app(ListConflictsAction::class)->execute('kb'))->toBe([]);
});

it('applies the starter migration larecipe:install writes for a new database set, once', function () use ($page) {
    $directory = (string) config('larecipe.migrations.path');
    $workspace = new LocalWorkspace(dirname($directory));
    $scaffold = app(ScaffoldDatabaseDocSetAction::class);

    expect($scaffold->execute($workspace, 'kb', basename($directory), 'Acme', '13.37.0', '2026_10_17_150000')->changed())->toBeTrue()
        ->and($scaffold->execute($workspace, 'kb', basename($directory), 'Acme', '13.37.0', '2026_10_18_090000')->changed())->toBeFalse()
        ->and(glob($directory.'/*_docs_introduction.php'))->toHaveCount(1);

    $this->artisan('migrate')->assertSuccessful();
    app()->forgetScopedInstances();

    expect($page('index')->title)->toBe('Introduction')
        ->and($page('index')->body)->toStartWith("# Acme\n")
        ->and(app(SourceManager::class)->for('kb')->manifest()->name)->toBe('Acme');

    $this->get('/kb')->assertOk()->assertSee('Acme');
});
