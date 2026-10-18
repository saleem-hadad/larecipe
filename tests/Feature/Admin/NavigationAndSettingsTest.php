<?php

use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;

uses(UsesAdminSets::class);

beforeEach(function () {
    AdminFixtures::page('index', "# Welcome\n");
    AdminFixtures::page('billing/refunds', "# Refunds\n");
    AdminFixtures::page('billing/exports', "# Exports\n", ['hidden' => true]);
    $this->actingAs(AdminFixtures::manager());
});

it('lists the page tree: the derived navigation, every page and its state', function () {
    $this->getJson('/kb/_admin/api/tree')
        ->assertOk()
        ->assertJsonPath('navigation.derived', true)
        ->assertJsonPath('navigation.content_hash', 'absent')
        ->assertJsonPath('navigation.tree.groups.0', 'index')
        ->assertJsonPath('navigation.tree.groups.1.group', 'Billing')
        ->assertJsonCount(3, 'pages')
        ->assertJsonPath('pages.0.path', 'billing/exports')
        ->assertJsonPath('pages.0.hidden', true)
        ->assertJsonPath('pages.0.fresh', true);
});

it('lists pages an agent proposed that do not exist yet', function () {
    AdminFixtures::agentDraft('billing/disputes', "# Disputes\n");

    $this->getJson('/kb/_admin/api/tree')->assertJsonPath('proposed.0.ref.path', 'billing/disputes')->assertJsonPath('proposed.0.status', 'draft');
});

it('saves the navigation from the tree editor, freezing the derived tree', function () {
    $tree = ['groups' => [['group' => 'Start', 'pages' => ['index']], ['group' => 'Money', 'pages' => ['billing/exports', 'billing/refunds']]]];

    $this->putJson('/kb/_admin/api/navigation', ['tree' => $tree, 'based_on' => 'absent'])
        ->assertOk()
        ->assertJsonPath('outcome', 'published')
        ->assertJsonPath('revision.subject_type', 'navigation')
        ->assertJsonPath('revision.channel', 'admin_ui')
        ->assertJsonPath('revision.author.name', 'Ada Reviewer');

    $this->getJson('/kb/_admin/api/tree')
        ->assertJsonPath('navigation.derived', false)
        ->assertJsonPath('navigation.tree.groups.1.group', 'Money')
        ->assertJsonPath('navigation.tree.groups.1.pages.0', 'billing/exports');

    expect(app(SourceManager::class)->for('kb')->navigation('current', 'en')->pagePaths())->toBe(['index', 'billing/exports', 'billing/refunds']);
});

it('patches the navigation when a page is added', function () {
    $hash = $this->getJson('/kb/_admin/api/tree')->json('navigation.content_hash');

    $this->putJson('/kb/_admin/api/navigation', ['patch' => [['add' => 'billing/exports', 'group' => 'Billing', 'after' => 'billing/refunds']], 'based_on' => $hash])->assertOk();

    expect(app(SourceManager::class)->for('kb')->navigation('current', 'en')->pagePaths())->toBe(['index', 'billing/refunds', 'billing/exports']);
});

it('never saves a tree over a newer navigation', function () {
    $this->putJson('/kb/_admin/api/navigation', ['tree' => ['groups' => ['index']]])->assertOk();

    $this->putJson('/kb/_admin/api/navigation', ['tree' => ['groups' => ['billing/refunds']], 'based_on' => 'absent'])
        ->assertStatus(409)
        ->assertJsonPath('outcome', 'conflicted');
});

it('reads and saves the set settings as a merge of what changed', function () {
    $this->getJson('/kb/_admin/api/manifest')->assertOk()->assertJsonPath('exists', false)->assertJsonPath('content_hash', 'absent');

    $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'Knowledge base', 'theme' => ['accent' => '#0f766e', 'mode' => 'system'], 'agents' => ['llmsTxt' => ['description' => 'Runbooks.']]], 'based_on' => 'absent'])
        ->assertOk()
        ->assertJsonPath('outcome', 'published')
        ->assertJsonPath('revision.subject_type', 'manifest')
        ->assertJsonPath('revision.channel', 'admin_ui');

    $manifest = $this->getJson('/kb/_admin/api/manifest')->assertJsonPath('manifest.name', 'Knowledge base')->json();

    // Removing a key from the form removes it from larecipe.json; untouched keys stay.
    $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'Knowledge base', 'theme' => ['accent' => '#0f766e']], 'based_on' => $manifest['content_hash']])->assertOk();

    expect(app(SourceManager::class)->for('kb')->manifest()->toArray())->toBe(['name' => 'Knowledge base', 'theme' => ['accent' => '#0f766e']]);
});

it('answers an unchanged manifest without a revision', function () {
    $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'KB']])->assertOk();

    $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'KB']])->assertOk()->assertJsonPath('outcome', 'unchanged')->assertJsonPath('revision', null);
});

it('refuses settings the docs could not be served with', function () {
    $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'KB', 'versions' => [['id' => '2.x'], ['id' => '2.x']]]])
        ->assertUnprocessable()
        ->assertJsonPath('outcome', 'rejected');
});
