<?php

use Illuminate\Support\Facades\Event;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Actions\DeletePageAction;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\GetRevisionHistoryAction;
use LaRecipe\Domains\Sources\Actions\ListConflictsAction;
use LaRecipe\Domains\Sources\Actions\ListDraftsAction;
use LaRecipe\Domains\Sources\Actions\MovePageAction;
use LaRecipe\Domains\Sources\Actions\PublishDraftAction;
use LaRecipe\Domains\Sources\Actions\RejectDraftAction;
use LaRecipe\Domains\Sources\Actions\RevertPageAction;
use LaRecipe\Domains\Sources\Actions\UpdateManifestAction;
use LaRecipe\Domains\Sources\Actions\UpdateNavigationAction;
use LaRecipe\Domains\Sources\Actions\WriteAssetAction;
use LaRecipe\Domains\Sources\DTOs\AssetUpload;
use LaRecipe\Domains\Sources\DTOs\ManifestDraft;
use LaRecipe\Domains\Sources\DTOs\NavigationDraft;
use LaRecipe\Domains\Sources\DTOs\PageDeletion;
use LaRecipe\Domains\Sources\DTOs\PageMove;
use LaRecipe\Domains\Sources\DTOs\RevertRequest;
use LaRecipe\Domains\Sources\DTOs\ReviewDecision;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Enums\RevisionStatus;
use LaRecipe\Domains\Sources\Enums\WriteOutcome;
use LaRecipe\Domains\Sources\Events\DraftCreated;
use LaRecipe\Domains\Sources\Events\DraftPublished;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;

uses(UsesDatabaseSets::class);

$ref = fn (string $path, string $version = 'current', string $locale = 'en') => new PageRef('kb', $version, $locale, $path);
$page = fn (string $path) => app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', $path));

it('stores pages as published revisions with provenance', function () use ($page) {
    $result = Fixtures::write('kb', 'billing/exports', "# Exports\n\nCSV downloads.\n", ['title' => 'Exports', 'sources' => ['app/Billing/**'], 'ratio' => 1.0]);

    expect($result->outcome)->toBe(WriteOutcome::Published)
        ->and($result->revision->id)->not->toBeNull()
        ->and($result->revision->channel)->toBe('pull_request')
        ->and($page('billing/exports')->body)->toBe("# Exports\n\nCSV downloads.\n")
        ->and($page('billing/exports')->frontmatter->get('ratio'))->toBe(1.0)
        ->and($page('billing/exports')->contentHash)->toBe($result->revision->contentHash)
        ->and($page('billing/exports')->provenance->actor)->toBe(Actor::Agent)
        ->and($page('billing/exports')->provenance->name)->toBe('claude-code')
        ->and(iterator_to_array(app(SourceManager::class)->for('kb')->pages('current', 'en')))->toHaveCount(1);
});

it('keeps remote agent changes as drafts until a person publishes them (review: required)', function () use ($page) {
    Event::fake([DraftCreated::class, DraftPublished::class]);

    $draft = Fixtures::write('kb', 'faq', "# FAQ\n", channel: Channel::RemoteAgent);

    expect($draft->outcome)->toBe(WriteOutcome::Drafted)
        ->and($page('faq'))->toBeNull()
        ->and(app(ListDraftsAction::class)->execute('kb'))->toHaveCount(1);

    Event::assertDispatched(DraftCreated::class);

    $published = app(PublishDraftAction::class)->execute(new ReviewDecision('kb', $draft->revision->id, Fixtures::human('Reviewer')));

    expect($published->outcome)->toBe(WriteOutcome::Published)
        ->and($published->revision->reviewedBy)->toBe('Reviewer')
        ->and($page('faq')->body)->toBe("# FAQ\n")
        ->and($page('faq')->provenance->approvedBy)->toBe('Reviewer')
        ->and(app(ListDraftsAction::class)->execute('kb'))->toBe([]);

    Event::assertDispatched(DraftPublished::class);
});

it('publishes remote agent changes at once under review: auto', function () use ($page) {
    config()->set('larecipe.review', 'auto');
    app()->forgetScopedInstances();

    expect(Fixtures::write('kb', 'faq', "# FAQ\n", channel: Channel::RemoteAgent)->outcome)->toBe(WriteOutcome::Published)
        ->and($page('faq'))->not->toBeNull();
});

it('rejects drafts with a reason and leaves live content alone', function () use ($page) {
    Fixtures::write('kb', 'faq', "# FAQ v1\n");
    $draft = Fixtures::write('kb', 'faq', "# FAQ v2\n", channel: Channel::RemoteAgent);

    $rejected = app(RejectDraftAction::class)->execute(new ReviewDecision('kb', $draft->revision->id, Fixtures::human(), 'Too vague.'));

    expect($rejected->status)->toBe('rejected')
        ->and($rejected->reviewNote)->toBe('Too vague.')
        ->and($page('faq')->body)->toBe("# FAQ v1\n");
});

it('reverts to an earlier revision as a new revision', function () use ($page, $ref) {
    $first = Fixtures::write('kb', 'faq', "# FAQ v1\n");
    $second = Fixtures::write('kb', 'faq', "# FAQ v2\n");

    $revert = app(RevertPageAction::class)->execute(new RevertRequest('kb', $first->revision->id, Fixtures::human()));
    $history = app(GetRevisionHistoryAction::class)->execute(Subject::page($ref('faq')));

    expect($revert->outcome)->toBe(WriteOutcome::Published)
        ->and($page('faq')->body)->toBe("# FAQ v1\n")
        ->and(array_map(fn ($r) => [$r->id, $r->status], $history))->toBe([
            [$revert->revision->id, 'published'],
            [$second->revision->id, 'reverted'],
            [$first->revision->id, 'superseded'],
        ])
        ->and($revert->revision->replaces)->toBe($second->revision->id);
});

it('moves pages and keeps their history across the move', function () use ($page, $ref) {
    Fixtures::write('kb', 'faq', "# FAQ\n");
    $move = app(MovePageAction::class)->execute(new PageMove($ref('faq'), $ref('help/faq'), Fixtures::agent(), Channel::PullRequest));

    expect($move->outcome)->toBe(WriteOutcome::Published)
        ->and($move->revision->refBefore->path)->toBe('faq')
        ->and($page('faq'))->toBeNull()
        ->and($page('help/faq')->body)->toBe("# FAQ\n")
        ->and(app(GetRevisionHistoryAction::class)->execute(Subject::page($ref('help/faq'))))->toHaveCount(2);
});

it('deletes pages and restores them from history', function () use ($page, $ref) {
    $written = Fixtures::write('kb', 'faq', "# FAQ\n");
    app(DeletePageAction::class)->execute(new PageDeletion($ref('faq'), Fixtures::human(), Channel::AdminUi));

    expect($page('faq'))->toBeNull();

    app(RevertPageAction::class)->execute(new RevertRequest('kb', $written->revision->id, Fixtures::human()));

    expect($page('faq')->body)->toBe("# FAQ\n");
});

it('stores navigation per version and locale, falling back to the default locale', function () {
    config()->set('larecipe.review', 'required');
    Fixtures::write('kb', 'index', "# Home\n");
    app(UpdateManifestAction::class)->execute(new ManifestDraft('kb', ['name' => 'KB', 'locales' => [['code' => 'en', 'default' => true], ['code' => 'ar']]], Fixtures::human(), Channel::AdminUi));

    $result = app(UpdateNavigationAction::class)->execute(new NavigationDraft('kb', 'current', 'en', Fixtures::human(), Channel::AdminUi, tree: NavigationTree::fromJson(['groups' => ['index']])));
    $source = app(SourceManager::class)->for('kb');

    expect($result->outcome)->toBe(WriteOutcome::Published)
        ->and($source->navigation('current', 'en')->toJson())->toBe(['groups' => ['index']])
        ->and($source->navigation('current', 'ar')->toJson())->toBe(['groups' => ['index']])
        ->and($source->manifest()->name)->toBe('KB');
});

it('serves assets from a filesystem disk', function () {
    app(WriteAssetAction::class)->execute(new AssetUpload('kb', 'current', '_assets/flow.png', 'PNG-BYTES'));
    $source = app(SourceManager::class)->for('kb');

    expect($source->asset('current', 'flow.png')->contents())->toBe('PNG-BYTES')
        ->and($source->asset('current', 'flow.png')->mime)->toBe('image/png')
        ->and(iterator_to_array($source->assets('current')))->toBe(['_assets/flow.png']);

    $this->get('/kb/_assets/current/flow.png')->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('moves its fingerprint on every publish, including a draft published late', function () {
    $source = app(SourceManager::class)->for('kb');
    $draft = Fixtures::write('kb', 'late', "# Late\n", channel: Channel::RemoteAgent);
    Fixtures::write('kb', 'early', "# Early\n");
    $before = $source->fingerprint();

    app(PublishDraftAction::class)->execute(new ReviewDecision('kb', $draft->revision->id, Fixtures::human()));

    expect($source->fingerprint())->not->toBe($before);
});

it('lists conflicts separately from drafts', function () {
    expect(app(ListConflictsAction::class)->execute('kb'))->toBe([])
        ->and(RevisionStatus::Conflict->isPending())->toBeTrue();
});
