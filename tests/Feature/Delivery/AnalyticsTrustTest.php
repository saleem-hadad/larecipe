<?php

/*
 * Analytics put third-party scripts on every page, where they run with every
 * reader's session. They come from config/larecipe.php for any set, and from
 * larecipe.json only for repository sets (reviewed in git). A database set's
 * manifest can be changed from the admin UI and MCP, so there they are refused
 * on write and ignored on read. Hosted providers load from their official
 * hosts unless config/larecipe.php points them at a self-hosted one.
 */

use LaRecipe\Domains\Admin\Actions\BuildBootPayloadAction;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Sources\Actions\UpdateManifestAction;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteOutcome;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\Services\SchemaValidator;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;

uses(UsesAdminSets::class);

const EVIL_ANALYTICS = [
    'custom' => ['src' => 'https://attacker.example/x.js'],
    'posthog' => ['apiKey' => 'phc_abcdefghijklmnop', 'host' => 'https://attacker.example'],
    'plausible' => ['domain' => 'kb.test', 'src' => 'https://attacker.example/p.js'],
];

beforeEach(function () {
    AdminFixtures::page('index', "# Welcome\n");
});

/** A manifest as an older release could store it, without the write checks. */
function storeKbManifest(array $manifest): void
{
    app(SourceManager::class)->for('kb')->writeManifest(Manifest::fromArray($manifest), Fixtures::human());
    app(SetRegistry::class)->flush();
}

function kbConfig(array $integrations): void
{
    config()->set('larecipe.sets.kb.integrations', $integrations);
    app()->forgetScopedInstances();
}

it('ignores integrations stored in a database set manifest', function () {
    storeKbManifest(['name' => 'KB', 'integrations' => ['analytics' => EVIL_ANALYTICS]]);

    $html = $this->get('/kb')->assertOk()->getContent();

    expect($html)->not->toContain('attacker.example')
        ->not->toContain('phc_abcdefghijklmnop')
        ->not->toContain("document.addEventListener('larecipe:feedback'");
});

it('takes a database set analytics from config/larecipe.php', function () {
    kbConfig(['analytics' => ['plausible' => ['domain' => 'kb.test']]]);

    expect($this->get('/kb')->assertOk()->getContent())
        ->toContain('<script defer data-domain="kb.test" src="https://plausible.io/js/script.js"></script>');
});

it('refuses integrations written to a database set manifest, and says where they go', function () {
    $result = app(UpdateManifestAction::class)->execute(new ManifestDraft('kb', ['name' => 'KB', 'integrations' => ['analytics' => EVIL_ANALYTICS]], Fixtures::agent(), Channel::RemoteAgent));

    expect($result->outcome)->toBe(WriteOutcome::Rejected)
        ->and($result->message)->toContain('config/larecipe.php')->toContain('sets.kb.integrations')
        ->and($result->validation->errors()[0]->rule)->toBe('manifest.integrations')
        ->and(app(SourceManager::class)->for('kb')->manifest())->toBeNull();
});

it('lets a database set remove integrations it already stores', function () {
    storeKbManifest(['name' => 'KB', 'integrations' => ['analytics' => EVIL_ANALYTICS]]);

    $result = app(UpdateManifestAction::class)->execute(new ManifestDraft('kb', ['integrations' => null], Fixtures::human(), Channel::PullRequest));

    expect($result->outcome)->toBe(WriteOutcome::Published)
        ->and(app(SourceManager::class)->for('kb')->manifest()->toArray())->toBe(['name' => 'KB']);
});

it('shows the refusal in the admin settings, whose form offers no integrations', function () {
    $this->actingAs(AdminFixtures::manager());

    $this->putJson('/kb/_admin/api/manifest', ['manifest' => ['name' => 'KB', 'integrations' => ['analytics' => EVIL_ANALYTICS]]])
        ->assertUnprocessable()
        ->assertJsonPath('outcome', 'rejected')
        ->assertJsonPath('message', fn (string $message) => str_contains($message, 'config/larecipe.php'));

    $schema = app(BuildBootPayloadAction::class)->execute(app(SetRegistry::class)->get('kb'), AdminFixtures::manager())['schemas']['manifest'];

    expect($schema['properties'])->not->toHaveKey('integrations')->toHaveKey('name');
});

it('keeps larecipe.json integrations for repository sets, on the providers\' official hosts', function () {
    $root = Fixtures::temporaryDirectory('analytics');
    file_put_contents($root.'/index.md', "# Home\n");
    file_put_contents($root.'/larecipe.json', json_encode(['name' => 'Repo', 'integrations' => ['analytics' => [
        'posthog' => ['apiKey' => 'phc_abcdefghijklmnop', 'host' => 'https://attacker.example'],
        'plausible' => ['domain' => 'repo.test', 'src' => 'https://attacker.example/p.js'],
        'fathom' => ['siteId' => 'ABCDEFGH'],
        'custom' => ['src' => 'https://cdn.repo.test/a.js'],
    ]]]));

    Sets::use(['repo' => [
        'route' => ['prefix' => 'repo', 'middleware' => ['web']],
        'source' => ['driver' => 'repository', 'path' => $root],
        'access' => ['mode' => 'public'],
    ]]);

    $html = $this->get('/repo')->assertOk()->getContent();

    expect($html)->not->toContain('src="https://attacker.example')
        ->not->toContain('api_host:"https://attacker.example"')
        ->toContain('src="https://cdn.usefathom.com/script.js"')
        ->toContain('<script defer src="https://cdn.repo.test/a.js"></script>');
});

it('loads a self-hosted provider only when config/larecipe.php sets the host', function () {
    kbConfig(['analytics' => [
        'posthog' => ['apiKey' => 'phc_abcdefghijklmnop', 'host' => 'https://ph.kb.test'],
        'plausible' => ['domain' => 'kb.test', 'src' => 'http://stats.kb.test/js/script.js'],
    ]]);

    expect($this->get('/kb')->assertOk()->getContent())
        ->toContain('api_host:"https://ph.kb.test"')
        ->not->toContain('http://stats.kb.test');
});

it('warns that a database set ignores the integrations its larecipe.json still holds', function () {
    storeKbManifest(['name' => 'KB', 'integrations' => ['analytics' => ['plausible' => ['domain' => 'kb.test']]]]);

    $issue = collect(app(ValidateDocsAction::class)->execute('kb')->warnings())->firstWhere('rule', 'manifest.integrations');

    expect($issue?->fix)->toContain('sets.kb.integrations');
});

it('reports a hosted provider pointed at another host in larecipe.json', function () {
    $errors = app(SchemaValidator::class)->errors(SchemaValidator::MANIFEST, ['integrations' => ['analytics' => [
        'plausible' => ['domain' => 'repo.test', 'src' => 'https://attacker.example/p.js'],
        'posthog' => ['apiKey' => 'phc_abcdefghijklmnop', 'host' => 'https://eu.i.posthog.com'],
    ]]]);

    expect($errors)->toHaveCount(1)->and($errors[0])->toContain('plausible');
});
