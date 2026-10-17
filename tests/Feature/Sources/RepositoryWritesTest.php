<?php

use Illuminate\Support\Facades\Event;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\DeletePageAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\MovePageAction;
use LaRecipe\Domains\Sources\Actions\UpdateManifestAction;
use LaRecipe\Domains\Sources\Actions\UpdateNavigationAction;
use LaRecipe\Domains\Sources\Actions\WriteAssetAction;
use LaRecipe\Domains\Sources\Actions\WritePageAction;
use LaRecipe\Domains\Sources\DTOs\AssetUpload;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\DTOs\NavigationDraft;
use LaRecipe\Domains\Sources\DTOs\PageDeletion;
use LaRecipe\Domains\Sources\DTOs\PageDraft;
use LaRecipe\Domains\Sources\DTOs\PageMove;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\WriteOutcome;
use LaRecipe\Domains\Sources\Events\ContentChanged;
use LaRecipe\Domains\Sources\Events\PageWritten;
use LaRecipe\Domains\Sources\Exceptions\UnsafePath;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesWritableRepositories;

uses(UsesWritableRepositories::class);

it('writes a new page to the working tree, validated and announced', function () {
    Event::fake([PageWritten::class, ContentChanged::class]);

    $result = Fixtures::write('docs', 'guides/retries', "# Retries\n\nAcme retries failed webhooks.\n", ['title' => 'Retries', 'sources' => ['app/Webhooks/**']]);

    $file = $this->setPath('docs').'/guides/retries.md';

    expect($result->outcome)->toBe(WriteOutcome::Published)
        ->and($result->revision->author->name)->toBe('claude-code')
        ->and(file_get_contents($file))->toBe("---\ntitle: Retries\nsources:\n  - 'app/Webhooks/**'\ndescription: 'A page written by a test.'\n---\n\n# Retries\n\nAcme retries failed webhooks.\n")
        ->and(app(GetPageAction::class)->execute(new PageRef('docs', 'current', 'en', 'guides/retries'))->contentHash)->toBe($result->revision->contentHash);

    Event::assertDispatched(PageWritten::class);
    Event::assertDispatched(ContentChanged::class, fn (ContentChanged $event) => $event->set === 'docs');
});

it('writes a new page whose path is a folder as that folder index', function () {
    Fixtures::write('guide', 'billing/plans', "# Plans\n", version: '2.x');
    app(SourceManager::class)->forget();
    unlink($this->setPath('guide').'/2.x/billing/index.md');
    app(SourceManager::class)->forget();

    Fixtures::write('guide', 'billing', "# Billing again\n", version: '2.x');

    expect(is_file($this->setPath('guide').'/2.x/billing/index.md'))->toBeTrue()
        ->and(is_file($this->setPath('guide').'/2.x/billing.md'))->toBeFalse();
});

it('refuses pages with validation errors and writes nothing', function () {
    $result = Fixtures::write('docs', 'empty', '   ');

    expect($result->outcome)->toBe(WriteOutcome::Rejected)
        ->and($result->validation->hasErrors())->toBeTrue()
        ->and(is_file($this->setPath('docs').'/empty.md'))->toBeFalse();
});

it('reports unchanged content without touching the file', function () {
    $page = app(GetPageAction::class)->execute(new PageRef('docs', 'current', 'en', 'installation'));
    $before = filemtime($this->setPath('docs').'/installation.md');

    $result = Fixtures::write('docs', 'installation', $page->body, $page->frontmatter->toArray());

    expect($result->outcome)->toBe(WriteOutcome::Unchanged)
        ->and(filemtime($this->setPath('docs').'/installation.md'))->toBe($before);
});

it('refuses to overwrite a page that changed since the writer read it', function () {
    $draft = new PageDraft(
        Page::make(new PageRef('docs', 'current', 'en', 'installation'), new Frontmatter(['title' => 'Installation', 'description' => 'x']), "# New\n"),
        Fixtures::agent(),
        Channel::PullRequest,
        basedOn: 'sha256:stale',
    );

    $result = app(WritePageAction::class)->execute($draft);

    expect($result->outcome)->toBe(WriteOutcome::Conflicted)
        ->and($result->revision)->toBeNull()
        ->and(file_get_contents($this->setPath('docs').'/installation.md'))->not->toContain('# New');
});

it('moves pages, also into another locale, and refuses a taken path', function () {
    $move = fn (string $from, string $to, string $locale = 'en') => app(MovePageAction::class)->execute(new PageMove(
        new PageRef('guide', '2.x', 'en', $from),
        new PageRef('guide', '2.x', $locale, $to),
        Fixtures::agent(),
        Channel::PullRequest,
    ));

    expect($move('billing/exports', 'billing/csv-exports')->outcome)->toBe(WriteOutcome::Published)
        ->and(is_file($this->setPath('guide').'/2.x/billing/csv-exports.md'))->toBeTrue()
        ->and(is_file($this->setPath('guide').'/2.x/billing/exports.md'))->toBeFalse()
        ->and($move('installation', 'installation', 'ar')->outcome)->toBe(WriteOutcome::Published)
        ->and(is_file($this->setPath('guide').'/2.x/ar/installation.md'))->toBeTrue()
        ->and($move('index', 'billing')->outcome)->toBe(WriteOutcome::Conflicted);
});

it('deletes a page', function () {
    $result = app(DeletePageAction::class)->execute(new PageDeletion(new PageRef('docs', 'current', 'en', 'guides/webhooks'), Fixtures::human(), Channel::PullRequest));

    expect($result->outcome)->toBe(WriteOutcome::Published)
        ->and(is_file($this->setPath('docs').'/guides/webhooks.md'))->toBeFalse()
        ->and($result->revision->payload['body'])->toContain('Webhooks');
});

it('freezes the derived navigation when the first patch arrives', function () {
    $result = app(UpdateNavigationAction::class)->execute(new NavigationDraft(
        'docs', 'current', 'en', Fixtures::agent(), Channel::PullRequest,
        patch: [['add' => 'guides/webhooks', 'group' => 'Guides', 'before' => 'guides']],
    ));

    $json = json_decode(file_get_contents($this->setPath('docs').'/navigation.json'), true);

    expect($result->outcome)->toBe(WriteOutcome::Published)
        ->and($json['groups'][0])->toBe('index')
        ->and(collect($json['groups'])->firstWhere('group', 'Guides')['pages'])->toBe(['guides/webhooks', 'guides']);
});

it('keeps $schema and warns about navigation entries without pages', function () {
    $result = app(UpdateNavigationAction::class)->execute(new NavigationDraft(
        'guide', '2.x', 'en', Fixtures::agent(), Channel::PullRequest,
        patch: [['add' => 'billing/refunds', 'group' => 'Billing']],
    ));

    $json = json_decode(file_get_contents($this->setPath('guide').'/2.x/navigation.json'), true);

    expect($json['$schema'])->toBe('https://larecipe.saleem.dev/schemas/3.x/navigation.json')
        ->and($json['groups'][1]['pages'])->toBe(['billing', 'billing/exports', 'billing/refunds'])
        ->and($result->validation->warnings()[0]->rule)->toBe('navigation.page.missing');
});

it('updates larecipe.json with a merge patch and keeps every other key', function () {
    $result = app(UpdateManifestAction::class)->execute(new ManifestDraft('guide', ['name' => 'Acme Docs', 'theme' => ['accent' => '#ff0000'], 'assets' => null], Fixtures::human(), Channel::PullRequest));

    $json = json_decode(file_get_contents($this->setPath('guide').'/larecipe.json'), true);

    expect($result->outcome)->toBe(WriteOutcome::Published)
        ->and($json['name'])->toBe('Acme Docs')
        ->and($json['theme'])->toBe(['accent' => '#ff0000', 'radius' => 'sharp'])
        ->and($json)->not->toHaveKey('assets')
        ->and($json['$schema'])->toBe('https://larecipe.saleem.dev/schemas/3.x/larecipe.json')
        ->and(file_get_contents($this->setPath('guide').'/larecipe.json'))->toStartWith("{\n  \"\$schema\"");
});

it('writes assets inside the version root and lists them', function () {
    app(WriteAssetAction::class)->execute(new AssetUpload('guide', '2.x', '_assets/diagram.svg', '<svg/>'));

    $source = app(SourceManager::class)->for('guide');

    expect(is_file($this->setPath('guide').'/2.x/_assets/diagram.svg'))->toBeTrue()
        ->and(iterator_to_array($source->assets('2.x')))->toBe(['_assets/diagram.svg', '_assets/flow.png'])
        ->and(iterator_to_array($source->assets('current')))->toBe(['_assets/logo.svg', 'theme.css'])
        ->and($source->asset('2.x', 'diagram.svg')?->contents())->toBe('<svg/>');
});

it('never writes outside the set root', function (string $path) {
    Fixtures::write('docs', $path, "# Evil\n");
})->throws(UnsafePath::class)->with(['../evil', 'guides/../../evil']);

it('never writes assets outside the set root, or over content files', function (string $path) {
    app(WriteAssetAction::class)->execute(new AssetUpload('docs', 'current', $path, 'x'));
})->throws(UnsafePath::class)->with(['../evil.png', 'larecipe.json', 'page.md']);
