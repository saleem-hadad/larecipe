<?php

use Illuminate\Http\UploadedFile;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;

uses(UsesAdminSets::class);

$live = fn (string $path) => app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', $path));

beforeEach(function () {
    AdminFixtures::page('billing/refunds', "# Refunds\n\nVersion one.\n");
    $this->actingAs(AdminFixtures::manager());
});

describe('revisions', function () use ($live) {
    it('lists a page\'s history, newest first, with what can be restored', function () {
        $this->putJson('/kb/_admin/api/page', ['path' => 'billing/refunds', 'frontmatter' => ['title' => 'Refunds'], 'body' => "# Refunds\n\nVersion two.\n"])->assertOk();

        $this->getJson('/kb/_admin/api/revisions?type=page&path=billing/refunds')
            ->assertOk()
            ->assertJsonPath('subject', 'current/en/billing/refunds')
            ->assertJsonCount(2, 'revisions')
            ->assertJsonPath('revisions.0.status', 'published')
            ->assertJsonPath('revisions.0.restorable', false)
            ->assertJsonPath('revisions.0.channel', 'admin_ui')
            ->assertJsonPath('revisions.1.status', 'superseded')
            ->assertJsonPath('revisions.1.restorable', true)
            ->assertJsonPath('revisions.1.channel', 'pull_request');
    });

    it('diffs a revision against the one it replaced', function () {
        $id = $this->putJson('/kb/_admin/api/page', ['path' => 'billing/refunds', 'frontmatter' => ['title' => 'Refunds'], 'body' => "# Refunds\n\nVersion two.\n"])->json('revision.id');

        $this->getJson("/kb/_admin/api/revisions/{$id}")
            ->assertOk()
            ->assertJsonPath('against', 'previous')
            ->assertJsonPath('proposed.html', "<p>Version two.</p>\n")
            ->assertJsonPath('current.html', "<p>Version one.</p>\n");
    });

    it('restores an earlier revision as a new one from the admin UI', function () use ($live) {
        $first = $this->getJson('/kb/_admin/api/revisions?type=page&path=billing/refunds')->json('revisions.0.id');
        $this->putJson('/kb/_admin/api/page', ['path' => 'billing/refunds', 'frontmatter' => ['title' => 'Refunds'], 'body' => "# Refunds\n\nVersion two.\n"])->assertOk();

        $this->postJson("/kb/_admin/api/revisions/{$first}/revert")
            ->assertOk()
            ->assertJsonPath('outcome', 'published')
            ->assertJsonPath('revision.channel', 'admin_ui')
            ->assertJsonPath('revision.author.name', 'Ada Reviewer')
            ->assertJsonPath('revision.summary', "Revert to revision #{$first}");

        expect($live('billing/refunds')->body)->toBe("# Refunds\n\nVersion one.\n");
    });

    it('brings a deleted page back', function () use ($live) {
        $first = $this->getJson('/kb/_admin/api/revisions?type=page&path=billing/refunds')->json('revisions.0.id');
        $this->deleteJson('/kb/_admin/api/page', ['path' => 'billing/refunds'])->assertOk();

        $this->postJson("/kb/_admin/api/revisions/{$first}/revert")->assertOk();

        expect($live('billing/refunds')->body)->toBe("# Refunds\n\nVersion one.\n");
    });

    it('refuses to restore what readers already see, or a draft', function () {
        $current = $this->getJson('/kb/_admin/api/revisions?type=page&path=billing/refunds')->json('revisions.0.id');
        $draft = AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nDraft.\n");

        $this->postJson("/kb/_admin/api/revisions/{$current}/revert")->assertUnprocessable();
        $this->postJson("/kb/_admin/api/revisions/{$draft->id}/revert")->assertUnprocessable();
    });

    it('keeps the history of the navigation and the manifest', function () {
        $this->putJson('/kb/_admin/api/navigation', ['tree' => ['groups' => ['billing/refunds']]])->assertOk();
        $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'KB']])->assertOk();
        $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'Knowledge base']])->assertOk();

        $this->getJson('/kb/_admin/api/revisions?type=navigation')->assertOk()->assertJsonCount(1, 'revisions')->assertJsonPath('subject', 'current/en');

        $old = $this->getJson('/kb/_admin/api/revisions?type=manifest')->assertJsonCount(2, 'revisions')->json('revisions.1.id');
        $this->getJson("/kb/_admin/api/revisions/{$old}")->assertJsonPath('kind', 'manifest')->assertJsonPath('proposed.manifest.name', 'KB');
        $this->postJson("/kb/_admin/api/revisions/{$old}/revert")->assertOk();

        expect(app(SourceManager::class)->for('kb')->manifest()->name)->toBe('KB');
    });

    it('asks which history to list', function () {
        $this->getJson('/kb/_admin/api/revisions?type=page')->assertUnprocessable()->assertJsonValidationErrors('path');
        $this->getJson('/kb/_admin/api/revisions?type=everything')->assertUnprocessable()->assertJsonValidationErrors('type');
    });
});

describe('assets', function () {
    it('uploads a file to the set\'s disk and lists it with its URL', function () {
        $this->post('/kb/_admin/api/assets', ['file' => UploadedFile::fake()->image('Flow Chart.png', 10, 10)], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('asset.path', '_assets/flow-chart.png')
            ->assertJsonPath('asset.mime', 'image/png')
            ->assertJsonPath('asset.url', '/kb/_assets/current/flow-chart.png')
            ->assertJsonPath('asset.markdown', '/_assets/flow-chart.png')
            ->assertJsonPath('asset.references', []);

        $this->getJson('/kb/_admin/api/assets')->assertOk()->assertJsonCount(1, 'assets');
        $this->get('/kb/_assets/current/flow-chart.png')->assertOk()->assertHeader('Content-Type', 'image/png');
    });

    it('refuses files that are not docs assets', function () {
        $this->post('/kb/_admin/api/assets', ['file' => UploadedFile::fake()->create('shell.php', 1, 'text/x-php')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    });

    it('will not delete an image a page still shows, unless asked twice', function () {
        $this->post('/kb/_admin/api/assets', ['file' => UploadedFile::fake()->image('flow.png', 10, 10)], ['Accept' => 'application/json'])->assertCreated();
        AdminFixtures::page('guides/flow', "# Flow\n\n![The flow](/_assets/flow.png)\n");

        $this->getJson('/kb/_admin/api/assets')->assertJsonPath('assets.0.references', ['guides/flow']);

        $this->deleteJson('/kb/_admin/api/assets', ['path' => '_assets/flow.png'])
            ->assertStatus(409)
            ->assertJsonPath('references', ['guides/flow']);

        $this->deleteJson('/kb/_admin/api/assets', ['path' => '_assets/flow.png', 'force' => true])->assertOk();
        $this->getJson('/kb/_admin/api/assets')->assertJsonCount(0, 'assets');
    });

    it('deletes an unused asset', function () {
        $this->post('/kb/_admin/api/assets', ['file' => UploadedFile::fake()->image('unused.png', 10, 10)], ['Accept' => 'application/json'])->assertCreated();

        $this->deleteJson('/kb/_admin/api/assets', ['path' => '_assets/unused.png'])->assertOk()->assertJsonPath('deleted', '_assets/unused.png');
    });
});
