<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\ListDraftsAction;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;

uses(UsesAdminSets::class);

$live = fn (string $path) => app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', $path));

beforeEach(function () {
    AdminFixtures::page('billing/refunds', "# Refunds\n\nMoney back.\n");
    $this->actingAs(AdminFixtures::manager());
});

it('reads a page with its source, hash and provenance', function () {
    $this->getJson('/kb/_admin/api/page?path=billing/refunds')
        ->assertOk()
        ->assertJsonPath('page.path', 'billing/refunds')
        ->assertJsonPath('page.title', 'Refunds')
        ->assertJsonPath('page.body', "# Refunds\n\nMoney back.\n")
        ->assertJsonPath('page.frontmatter.title', 'Refunds')
        ->assertJsonPath('page.provenance.actor', 'migration')
        ->assertJsonPath('page.url', 'http://localhost/kb/billing/refunds')
        ->assertJsonPath('pending', []);
});

it('says when a page does not exist', function () {
    $this->getJson('/kb/_admin/api/page?path=nowhere')->assertNotFound()->assertJsonPath('message', 'There is no page at [nowhere] in current/en.');
});

it('creates a page as a published revision from the admin UI, with the person as author', function () use ($live) {
    $this->postJson('/kb/_admin/api/page', [
        'path' => 'billing/exports',
        'frontmatter' => ['title' => 'Exports', 'description' => 'CSV downloads.'],
        'body' => "# Exports\n\nDownload a CSV.\n",
    ])
        ->assertCreated()
        ->assertJsonPath('outcome', 'published')
        ->assertJsonPath('revision.channel', 'admin_ui')
        ->assertJsonPath('revision.status', 'published')
        ->assertJsonPath('revision.author.actor', 'human')
        ->assertJsonPath('revision.author.name', 'Ada Reviewer')
        ->assertJsonPath('revision.summary', 'Create billing/exports');

    expect($live('billing/exports')->body)->toBe("# Exports\n\nDownload a CSV.\n")
        ->and($live('billing/exports')->provenance->name)->toBe('Ada Reviewer');
});

it('keeps the final newline of a page body', function () use ($live) {
    $this->postJson('/kb/_admin/api/page', ['path' => 'faq', 'frontmatter' => ['title' => 'FAQ', 'description' => 'Answers.'], 'body' => "# FAQ\n\n"])->assertCreated();

    expect($live('faq')->body)->toBe("# FAQ\n\n");
});

it('refuses a new page on a path that is taken', function () {
    $this->postJson('/kb/_admin/api/page', ['path' => 'billing/refunds', 'body' => "# Again\n"])
        ->assertStatus(409)
        ->assertJsonPath('outcome', 'conflicted');
});

it('refuses paths that are not lowercase, slash-separated words', function () {
    $this->postJson('/kb/_admin/api/page', ['path' => '../Secrets', 'body' => "# Hi\n"])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['path' => 'Use lowercase letters, numbers, dashes and slashes for the path, for example billing/refunds.']);
});

it('refuses a page with validation errors and writes nothing', function () use ($live) {
    $this->postJson('/kb/_admin/api/page', ['path' => 'broken', 'frontmatter' => ['title' => 'Broken'], 'body' => "# Broken\n\n<Nonexistent>hi</Nonexistent>\n"])
        ->assertUnprocessable()
        ->assertJsonPath('outcome', 'rejected')
        ->assertJsonPath('validation.ok', false);

    expect($live('broken'))->toBeNull();
});

it('updates a page against the hash it read', function () use ($live) {
    $hash = $live('billing/refunds')->contentHash;

    $this->putJson('/kb/_admin/api/page', [
        'path' => 'billing/refunds',
        'based_on' => $hash,
        'frontmatter' => ['title' => 'Refunds', 'description' => 'Getting money back.'],
        'body' => "# Refunds\n\nMoney back within 30 days.\n",
        'summary' => 'Say how long refunds take',
    ])
        ->assertOk()
        ->assertJsonPath('outcome', 'published')
        ->assertJsonPath('revision.channel', 'admin_ui')
        ->assertJsonPath('revision.based_on_hash', $hash)
        ->assertJsonPath('revision.summary', 'Say how long refunds take');

    expect($live('billing/refunds')->body)->toContain('within 30 days');
});

it('never overwrites a page that changed since it was read', function () use ($live) {
    $stale = $live('billing/refunds')->contentHash;
    AdminFixtures::page('billing/refunds', "# Refunds\n\nChanged by someone else.\n");

    $this->putJson('/kb/_admin/api/page', ['path' => 'billing/refunds', 'based_on' => $stale, 'frontmatter' => ['title' => 'Refunds'], 'body' => "# Refunds\n\nMine.\n"])
        ->assertStatus(409)
        ->assertJsonPath('outcome', 'conflicted');

    expect($live('billing/refunds')->body)->toContain('Changed by someone else.');
});

it('saves a draft for review without changing the live page', function () use ($live) {
    $this->putJson('/kb/_admin/api/page', ['path' => 'billing/refunds', 'mode' => 'draft', 'frontmatter' => ['title' => 'Refunds'], 'body' => "# Refunds\n\nDraft.\n"])
        ->assertOk()
        ->assertJsonPath('outcome', 'drafted')
        ->assertJsonPath('revision.status', 'draft')
        ->assertJsonPath('revision.channel', 'admin_ui');

    expect($live('billing/refunds')->body)->toBe("# Refunds\n\nMoney back.\n")
        ->and(app(ListDraftsAction::class)->execute('kb'))->toHaveCount(1);

    $this->getJson('/kb/_admin/api/page?path=billing/refunds')->assertJsonCount(1, 'pending');
});

it('moves a page', function () use ($live) {
    $this->postJson('/kb/_admin/api/page/move', ['path' => 'billing/refunds', 'to' => 'payments/refunds'])
        ->assertOk()
        ->assertJsonPath('revision.operation', 'move')
        ->assertJsonPath('revision.ref_before.path', 'billing/refunds')
        ->assertJsonPath('revision.ref.path', 'payments/refunds')
        ->assertJsonPath('revision.channel', 'admin_ui');

    expect($live('billing/refunds'))->toBeNull()->and($live('payments/refunds')->body)->toBe("# Refunds\n\nMoney back.\n");
});

it('refuses to move a page onto another page', function () {
    AdminFixtures::page('payments/refunds', "# Taken\n");

    $this->postJson('/kb/_admin/api/page/move', ['path' => 'billing/refunds', 'to' => 'payments/refunds'])->assertStatus(409);
});

it('deletes a page and keeps its history', function () use ($live) {
    $this->deleteJson('/kb/_admin/api/page', ['path' => 'billing/refunds'])
        ->assertOk()
        ->assertJsonPath('revision.operation', 'delete')
        ->assertJsonPath('revision.channel', 'admin_ui');

    expect($live('billing/refunds'))->toBeNull();

    $this->getJson('/kb/_admin/api/revisions?type=page&path=billing/refunds')->assertOk()->assertJsonCount(2, 'revisions');
});

it('says so when deleting a page that is not there', function () {
    $this->deleteJson('/kb/_admin/api/page', ['path' => 'nowhere'])->assertUnprocessable()->assertJsonPath('outcome', 'rejected');
});

it('validates the version and locale it is asked about', function () {
    $this->getJson('/kb/_admin/api/page?path=billing/refunds&version=9.x')->assertUnprocessable()->assertJsonValidationErrors('version');
});
