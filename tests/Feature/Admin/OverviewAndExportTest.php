<?php

use Illuminate\Support\Facades\Session;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\ListConflictsAction;
use LaRecipe\Domains\Sources\Enums\DocsMigrationOperationType;
use LaRecipe\Migrations\DocsMigration;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;

uses(UsesAdminSets::class);

beforeEach(function () {
    AdminFixtures::page('index', "# Welcome\n");
    $this->actingAs(AdminFixtures::manager());
});

describe('the shell', function () {
    it('mounts the admin app with its boot payload, never cached or indexed', function () {
        $response = $this->get('/kb/_admin/review')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<div id="larecipe-admin"', false);

        preg_match('#<script type="application/json" id="lr-admin-boot">(.*?)</script>#s', $response->getContent(), $matches);
        $boot = json_decode($matches[1], true);

        expect($boot['set'])->toMatchArray(['key' => 'kb', 'driver' => 'database', 'review' => 'required'])
            ->and($boot['user'])->toBe(['name' => 'Ada Reviewer'])
            ->and($boot['permissions'])->toBe(['manage' => true, 'edit' => true, 'review' => true])
            ->and($boot['urls']['api'])->toBe('/kb/_admin/api')
            ->and($boot['versions'][0]['id'])->toBe('current')
            ->and($boot['locales'][0])->toMatchArray(['code' => 'en', 'dir' => 'ltr'])
            ->and(array_column($boot['components'], 'name'))->toContain('Callout', 'Steps', 'Tabs')
            ->and($boot['components'][array_search('Callout', array_column($boot['components'], 'name'))]['props'])->toHaveKey('type')
            ->and(array_keys($boot['schemas']))->toBe(['frontmatter', 'manifest', 'navigation'])
            ->and($boot['schemas']['frontmatter']['properties'])->toHaveKey('title');
    });

    it('tells a repository set\'s admin it may not edit', function () {
        $response = $this->get('/docs/_admin')->assertOk();
        preg_match('#id="lr-admin-boot">(.*?)</script>#s', $response->getContent(), $matches);

        expect(json_decode($matches[1], true)['permissions'])->toBe(['manage' => true, 'edit' => false, 'review' => false]);
    });
});

describe('the overview', function () {
    it('counts pages and what waits at the pass, with the validation report', function () {
        AdminFixtures::agentDraft('faq', "# FAQ\n");
        AdminFixtures::conflict('support/escalation');

        $this->getJson('/kb/_admin/api/overview')
            ->assertOk()
            ->assertJsonPath('driver', 'database')
            ->assertJsonPath('counts', ['pages' => 2, 'conflicts' => 1, 'drafts' => 1])
            ->assertJsonPath('validation.summary.pages', 2)
            ->assertJsonStructure(['validation' => ['ok', 'summary', 'issues']]);
    });

    it('grades agent readiness without signing the reviewer out', function () {
        Session::put('larecipe.test', 'kept');

        $this->getJson('/kb/_admin/api/readiness')
            ->assertOk()
            ->assertJsonPath('set', 'kb')
            ->assertJsonStructure(['grade', 'score', 'checks' => [['id', 'title', 'status', 'message']]]);

        expect(Session::get('larecipe.test'))->toBe('kept');
        $this->assertAuthenticatedAs(AdminFixtures::manager());
        $this->getJson('/kb/_admin/api/overview')->assertOk();
    });
});

describe('exporting production changes as a docs migration', function () {
    it('lists the set\'s activity and marks what git does not have yet', function () {
        $this->postJson('/kb/_admin/api/page', ['path' => 'faq', 'frontmatter' => ['title' => 'FAQ', 'description' => 'Answers.'], 'body' => "# FAQ\n"])->assertCreated();

        $this->getJson('/kb/_admin/api/activity')
            ->assertOk()
            ->assertJsonPath('revisions.0.ref.path', 'faq')
            ->assertJsonPath('revisions.0.exportable', true)
            ->assertJsonPath('revisions.1.ref.path', 'index')
            ->assertJsonPath('revisions.1.exportable', false)
            ->assertJsonPath('next', null);
    });

    it('writes one docs migration that replays the changes, folded and against their hashes', function () {
        $seeded = $this->getJson('/kb/_admin/api/activity')->json('revisions.0.id');
        $created = $this->postJson('/kb/_admin/api/page', ['path' => 'faq', 'frontmatter' => ['title' => 'FAQ', 'description' => 'Answers.'], 'body' => "# FAQ\n"])->json('revision.id');
        $updated = $this->putJson('/kb/_admin/api/page', ['path' => 'faq', 'frontmatter' => ['title' => 'FAQ', 'description' => 'Answers.'], 'body' => "# FAQ\n\nAsk away.\n"])->json('revision.id');
        $index = app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', 'index'))->contentHash;
        $edited = $this->putJson('/kb/_admin/api/page', ['path' => 'index', 'frontmatter' => ['title' => 'Index'], 'body' => "# Welcome\n\nStart here.\n"])->json('revision.id');
        $settings = $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'Knowledge base']])->json('revision.id');

        $export = $this->postJson('/kb/_admin/api/export', ['revisions' => [$settings, $edited, $updated, $created, $seeded], 'name' => 'Admin edits', 'summary' => 'Bring production edits into git'])
            ->assertOk()
            ->assertJsonPath('operations', 4)
            ->assertJsonPath('included', [$created, $updated, $edited, $settings])
            ->assertJsonPath('skipped.0.id', $seeded)
            ->json();

        expect($export['name'])->toEndWith('_docs_admin_edits')
            ->and($export['file'])->toEndWith("{$export['name']}.php")
            ->and($export['source'])->toContain("protected string \$author = 'Ada Reviewer';")
            ->and($export['source'])->toContain("protected string \$summary = 'Bring production edits into git';");

        // The file is a real docs migration: the same plan the DocsMigration base class would run.
        $file = config('larecipe.migrations.path').'/'.$export['name'].'.php';
        file_put_contents($file, $export['source']);
        $migration = require $file;
        $operations = $migration->plan()->operations;

        expect($migration)->toBeInstanceOf(DocsMigration::class)
            ->and(array_map(fn ($operation) => $operation->type, $operations))->toBe([DocsMigrationOperationType::CreatePage, DocsMigrationOperationType::UpdatePage, DocsMigrationOperationType::UpdateManifest])
            ->and($operations[0]->target)->toBe('faq')
            ->and($operations[0]->body)->toBe("# FAQ\n\nAsk away.\n")
            ->and($operations[1]->basedOn)->toBe($index)
            ->and($operations[2]->patch)->toBe(['name' => 'Knowledge base']);

        // Applied where the changes already are (production), it changes nothing and raises no conflict.
        $this->artisan('migrate', ['--database' => 'larecipe'])->assertSuccessful();

        expect(app(ListConflictsAction::class)->execute('kb'))->toBe([])
            ->and(app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', 'index'))->body)->toBe("# Welcome\n\nStart here.\n");
    });

    it('says when nothing chosen can be exported', function () {
        $seeded = $this->getJson('/kb/_admin/api/activity')->json('revisions.0.id');

        $this->postJson('/kb/_admin/api/export', ['revisions' => [$seeded]])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'None of these changes can be exported as a docs migration.')
            ->assertJsonPath('skipped.0.reason', 'It came from a pull request or a docs migration, so git already has it.');
    });
});
