<?php

use Illuminate\Support\Facades\Gate;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;

uses(UsesAdminSets::class);

/** Every admin endpoint, with a body that would be valid for a manager. */
dataset('endpoints', [
    'shell' => ['GET', '/kb/_admin', []],
    'shell deep link' => ['GET', '/kb/_admin/pages/billing/refunds', []],
    'overview' => ['GET', '/kb/_admin/api/overview', []],
    'readiness' => ['GET', '/kb/_admin/api/readiness', []],
    'stale' => ['GET', '/kb/_admin/api/stale', []],
    'tree' => ['GET', '/kb/_admin/api/tree', []],
    'navigation' => ['PUT', '/kb/_admin/api/navigation', ['tree' => ['groups' => ['index']]]],
    'page' => ['GET', '/kb/_admin/api/page?path=index', []],
    'create page' => ['POST', '/kb/_admin/api/page', ['path' => 'new', 'body' => "# New\n"]],
    'update page' => ['PUT', '/kb/_admin/api/page', ['path' => 'index', 'body' => "# Index\n"]],
    'move page' => ['POST', '/kb/_admin/api/page/move', ['path' => 'index', 'to' => 'start']],
    'delete page' => ['DELETE', '/kb/_admin/api/page', ['path' => 'index']],
    'preview' => ['POST', '/kb/_admin/api/preview', ['body' => "# Hi\n"]],
    'validate' => ['POST', '/kb/_admin/api/validate', ['body' => "# Hi\n"]],
    'manifest' => ['GET', '/kb/_admin/api/manifest', []],
    'update manifest' => ['PUT', '/kb/_admin/api/manifest', ['manifest' => ['name' => 'KB']]],
    'review queue' => ['GET', '/kb/_admin/api/review', []],
    'review show' => ['GET', '/kb/_admin/api/review/1', []],
    'publish' => ['POST', '/kb/_admin/api/review/1/publish', []],
    'reject' => ['POST', '/kb/_admin/api/review/1/reject', ['reason' => 'No']],
    'edit draft' => ['POST', '/kb/_admin/api/review/1/edit', ['body' => "# Hi\n"]],
    'revisions' => ['GET', '/kb/_admin/api/revisions?type=manifest', []],
    'revision' => ['GET', '/kb/_admin/api/revisions/1', []],
    'revert' => ['POST', '/kb/_admin/api/revisions/1/revert', []],
    'activity' => ['GET', '/kb/_admin/api/activity', []],
    'export' => ['POST', '/kb/_admin/api/export', ['revisions' => [1]]],
    'assets' => ['GET', '/kb/_admin/api/assets', []],
    'delete asset' => ['DELETE', '/kb/_admin/api/assets', ['path' => '_assets/flow.png']],
]);

beforeEach(function () {
    AdminFixtures::page('index', "# Index\n\nHello.\n");
});

it('refuses guests with a 403', function (string $method, string $url, array $body) {
    $this->json($method, $url, $body)->assertForbidden();
})->with('endpoints');

it('refuses signed-in users the manageLarecipe gate does not let in', function (string $method, string $url, array $body) {
    $this->actingAs(AdminFixtures::stranger())->json($method, $url, $body)->assertForbidden();
})->with('endpoints');

it('lets a manager in to every endpoint', function (string $method, string $url, array $body) {
    $status = $this->actingAs(AdminFixtures::manager())->json($method, $url, $body)->status();

    // Past the gate: whatever the endpoint decides about the request itself, never a 403.
    expect($status)->not->toBe(403)->and($status)->toBeLessThan(500);
})->with('endpoints');

it('asks the gate about this set', function () {
    $asked = [];
    Gate::define('manageLarecipe', function ($user, string $set) use (&$asked) {
        $asked[] = $set;

        return $set === 'kb';
    });

    $this->actingAs(AdminFixtures::stranger())->getJson('/kb/_admin/api/overview')->assertOk();
    $this->actingAs(AdminFixtures::stranger())->getJson('/docs/_admin/api/overview')->assertForbidden();

    expect($asked)->toBe(['kb', 'docs']);
});

it('keeps the admin closed outside local environments when the host defines no gate', function () {
    // A gate without abilities, as in an app that never defined manageLarecipe (the test environment is not local).
    app()->instance(Illuminate\Contracts\Auth\Access\Gate::class, new Illuminate\Auth\Access\Gate(app(), fn () => null));
    app()->forgetScopedInstances();

    $this->actingAs(AdminFixtures::manager())->getJson('/kb/_admin/api/overview')->assertForbidden();
});

it('has no admin for a doc set that does not exist', function () {
    $this->actingAs(AdminFixtures::manager())->get('/nope/_admin')->assertNotFound();
    $this->actingAs(AdminFixtures::manager())->getJson('/nope/_admin/api/overview')->assertNotFound();
});

it('answers an unknown API path with JSON', function () {
    $this->actingAs(AdminFixtures::manager())
        ->getJson('/kb/_admin/api/nothing-here')
        ->assertNotFound()
        ->assertJson(['message' => 'There is no such admin endpoint.']);
});

it('keeps repository sets read-only: an overview, no editor', function () {
    $this->actingAs(AdminFixtures::manager());

    $this->getJson('/docs/_admin/api/overview')->assertOk()->assertJsonPath('driver', 'repository');
    $this->getJson('/docs/_admin/api/stale')->assertOk()->assertJsonStructure(['summary', 'pages', 'drift', 'notes']);

    foreach ([['GET', '/docs/_admin/api/tree', []], ['PUT', '/docs/_admin/api/page', ['path' => 'index', 'body' => "# Hi\n"]], ['GET', '/docs/_admin/api/review', []]] as [$method, $url, $body]) {
        $this->json($method, $url, $body)
            ->assertForbidden()
            ->assertJsonPath('message', 'The [docs] docs live in the repository, so they are edited in git and reviewed in the pull request. The admin shows them read-only.');
    }
});
