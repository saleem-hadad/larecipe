<?php

/*
 * Navigation, navbar, footer, call-to-action and repository links come from
 * content. A javascript: link in the server HTML runs before the UI loads,
 * or with JavaScript off, so links other than http, https, mailto and
 * relative ones never reach a page, validation reports them, and database
 * writes that add them are refused.
 */

use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;

uses(UsesAdminSets::class);

function evilLinksSet(): string
{
    $root = Fixtures::temporaryDirectory('links');
    file_put_contents($root.'/index.md', "# Home\n\nWelcome.\n");
    file_put_contents($root.'/larecipe.json', json_encode([
        'name' => 'Evil',
        'navbar' => ['links' => [['label' => 'NavbarJS', 'href' => "javascript:alert('navbar')"], ['label' => 'Changelog', 'href' => '/changelog']], 'cta' => ['label' => 'Cta', 'href' => "javascript:alert('cta')"]],
        'footer' => ['links' => [['label' => 'FooterJS', 'href' => "java\tscript:alert('footer')"]]],
        'repository' => ['url' => "javascript:alert('repo')"],
    ]));
    file_put_contents($root.'/navigation.json', json_encode(['groups' => [
        ['group' => 'Start', 'pages' => ['index']],
        ['label' => 'NavJS', 'href' => 'javascript:alert(document.domain)'],
        ['label' => 'Elsewhere', 'href' => '//evil.example/'],
    ]]));

    Sets::use(['evil' => [
        'route' => ['prefix' => 'evil', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);

    return $root;
}

it('never puts a script or protocol-relative link in the page, its boot payload or its JSON', function () {
    evilLinksSet();

    $html = $this->get('/evil')->assertOk()->getContent();
    $json = $this->get('/evil', ['Accept' => 'application/vnd.larecipe.page+json'])->assertOk()->getContent();

    foreach ([$html, $json] as $body) {
        expect(strtolower($body))->not->toContain('javascript:')
            ->not->toContain('script:alert')
            ->not->toContain('evil.example');
    }

    expect($html)->toContain('href="/changelog"');
});

it('reports every refused link when validating', function () {
    evilLinksSet();

    $issues = collect(app(ValidateDocsAction::class)->execute('evil')->errors())->where('rule', 'links.unsafe');

    expect($issues)->toHaveCount(6)
        ->and($issues->pluck('file')->unique()->sort()->values()->all())->toBe(['larecipe.json', 'navigation.json']);
});

it('refuses database writes that add such links', function () {
    AdminFixtures::page('index', "# Welcome\n");
    $this->actingAs(AdminFixtures::manager());

    $this->putJson('/kb/_admin/api/navigation', ['tree' => ['groups' => ['index', ['label' => 'x', 'href' => 'javascript:alert(1)']]], 'based_on' => 'absent'])
        ->assertUnprocessable()
        ->assertJsonPath('outcome', 'rejected')
        ->assertJsonPath('validation.issues.0.rule', 'links.unsafe');

    $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'KB', 'navbar' => ['cta' => ['label' => 'Go', 'href' => 'javascript:alert(1)']]]])
        ->assertUnprocessable()
        ->assertJsonPath('validation.issues.0.rule', 'links.unsafe');
});
