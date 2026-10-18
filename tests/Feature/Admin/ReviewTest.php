<?php

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Actions\GetRevisionAction;
use LaRecipe\Domains\Sources\Actions\ListConflictsAction;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;

uses(UsesAdminSets::class);

$live = fn (string $path) => app(GetPageAction::class)->execute(new PageRef('kb', 'current', 'en', $path));
$revision = fn (string $id) => app(GetRevisionAction::class)->execute('kb', $id);

beforeEach(function () {
    AdminFixtures::page('billing/refunds', "# Refunds\n\nMoney back.\n");
    $this->actingAs(AdminFixtures::manager());
});

it('puts conflicts first, then drafts, as tickets', function () {
    $draft = AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nMoney back within 30 days.\n");
    $conflict = AdminFixtures::conflict('support/escalation');

    $this->getJson('/kb/_admin/api/review')
        ->assertOk()
        ->assertJsonPath('mode', 'required')
        ->assertJsonPath('conflicts.0.id', $conflict->id)
        ->assertJsonPath('conflicts.0.ticket', str_pad($conflict->id, 4, '0', STR_PAD_LEFT))
        ->assertJsonPath('conflicts.0.migration', '2026_10_17_141502_docs_pagerduty')
        ->assertJsonPath('conflicts.0.title', 'Escalation')
        ->assertJsonPath('drafts.0.id', $draft->id)
        ->assertJsonPath('drafts.0.author.actor', 'agent')
        ->assertJsonPath('drafts.0.author.name', 'claude-code')
        ->assertJsonPath('drafts.0.channel', 'remote_agent')
        ->assertJsonPath('recent', []);
});

it('shows a draft next to production, rendered and as source', function () {
    $draft = AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nMoney back within 30 days.\n");

    $this->getJson("/kb/_admin/api/review/{$draft->id}")
        ->assertOk()
        ->assertJsonPath('kind', 'page')
        ->assertJsonPath('against', 'production')
        ->assertJsonPath('proposed.html', "<p>Money back within 30 days.</p>\n")
        ->assertJsonPath('current.html', "<p>Money back.</p>\n")
        ->assertJsonPath('proposed.source', "---\ntitle: Refunds\ndescription: 'A page written by a test.'\n---\n\n# Refunds\n\nMoney back within 30 days.\n");
});

it('publishes a draft, signed off by the reviewer', function () use ($live) {
    $draft = AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nMoney back within 30 days.\n");

    $this->postJson("/kb/_admin/api/review/{$draft->id}/publish")
        ->assertOk()
        ->assertJsonPath('outcome', 'published')
        ->assertJsonPath('revision.reviewed_by', 'Ada Reviewer');

    expect($live('billing/refunds')->body)->toContain('within 30 days')
        ->and($live('billing/refunds')->provenance->name)->toBe('claude-code')
        ->and($live('billing/refunds')->provenance->approvedBy)->toBe('Ada Reviewer');

    $this->postJson("/kb/_admin/api/review/{$draft->id}/publish")->assertStatus(409);
});

it('rejects a draft with a reason the agent can read', function () use ($live, $revision) {
    $draft = AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nWrong.\n");

    $this->postJson("/kb/_admin/api/review/{$draft->id}/reject", ['reason' => 'Refunds take 30 days, not 3.'])
        ->assertOk()
        ->assertJsonPath('revision.status', 'rejected');

    expect($revision($draft->id)->reviewNote)->toBe('Refunds take 30 days, not 3.')
        ->and($live('billing/refunds')->body)->toBe("# Refunds\n\nMoney back.\n");
});

it('edits a draft and publishes the reviewer\'s version instead', function () use ($live, $revision) {
    $draft = AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nMoney back within 30 dayz.\n");

    $this->postJson("/kb/_admin/api/review/{$draft->id}/edit", ['frontmatter' => ['title' => 'Refunds', 'description' => 'Getting money back.'], 'body' => "# Refunds\n\nMoney back within 30 days.\n"])
        ->assertOk()
        ->assertJsonPath('outcome', 'published')
        ->assertJsonPath('revision.channel', 'admin_ui')
        ->assertJsonPath('revision.author.name', 'Ada Reviewer');

    expect($live('billing/refunds')->body)->toBe("# Refunds\n\nMoney back within 30 days.\n")
        ->and($revision($draft->id)->status)->toBe('rejected')
        ->and($revision($draft->id)->reviewNote)->toStartWith('Replaced by revision #');
});

it('says when a revision is not in this set', function () {
    $this->getJson('/kb/_admin/api/review/999')->assertNotFound();
    $this->postJson('/kb/_admin/api/review/999/publish')->assertNotFound();
});

describe('docs migration conflicts', function () use ($live, $revision) {
    it('shows production as it was when the migration ran next to the migration\'s version', function () {
        $conflict = AdminFixtures::conflict('support/escalation');

        $this->getJson("/kb/_admin/api/review/{$conflict->id}")
            ->assertOk()
            ->assertJsonPath('against', 'conflict')
            ->assertJsonPath('revision.is_conflict', true)
            ->assertJsonPath('proposed.body', "# Escalation\n\nPage the on-call engineer through PagerDuty.\n")
            ->assertJsonPath('current.body', "# Escalation\n\nPage the on-call engineer, then open an incident.\n");
    });

    it('keeps production', function () use ($live, $revision) {
        $conflict = AdminFixtures::conflict('support/escalation');

        $this->postJson("/kb/_admin/api/review/{$conflict->id}/reject")->assertOk();

        expect($live('support/escalation')->body)->toContain('then open an incident')
            ->and($revision($conflict->id)->reviewNote)->toBe('Kept production.')
            ->and(app(ListConflictsAction::class)->execute('kb'))->toBe([]);
    });

    it('takes the migration', function () use ($live) {
        $conflict = AdminFixtures::conflict('support/escalation');

        $this->postJson("/kb/_admin/api/review/{$conflict->id}/publish")->assertOk()->assertJsonPath('outcome', 'published');

        expect($live('support/escalation')->body)->toContain('through PagerDuty');
    });

    it('edits a merge, written against production as the migration saw it', function () use ($live, $revision) {
        $conflict = AdminFixtures::conflict('support/escalation');
        $merge = "# Escalation\n\nPage the on-call engineer through PagerDuty, then open an incident.\n";

        $this->postJson("/kb/_admin/api/review/{$conflict->id}/edit", ['frontmatter' => ['title' => 'Escalation', 'description' => 'Who to call.'], 'body' => $merge])
            ->assertOk()
            ->assertJsonPath('revision.channel', Channel::AdminUi->value)
            ->assertJsonPath('revision.based_on_hash', $conflict->conflict['content_hash'])
            ->assertJsonPath('revision.summary', "Merge of conflict #{$conflict->id} (2026_10_17_141502_docs_pagerduty)");

        expect($live('support/escalation')->body)->toBe($merge)
            ->and($revision($conflict->id)->status)->toBe('rejected')
            ->and(app(ListConflictsAction::class)->execute('kb'))->toBe([]);
    });

    it('never loses a production edit made after the conflict was raised', function () use ($live) {
        $conflict = AdminFixtures::conflict('support/escalation');
        Fixtures::write('kb', 'support/escalation', "# Escalation\n\nEdited again.\n", ['title' => 'Escalation'], Channel::AdminUi, Fixtures::human('Someone'));

        $this->postJson("/kb/_admin/api/review/{$conflict->id}/edit", ['body' => "# Escalation\n\nMerged.\n"])
            ->assertStatus(409)
            ->assertJsonPath('outcome', 'conflicted');

        expect($live('support/escalation')->body)->toBe("# Escalation\n\nEdited again.\n")
            ->and(app(ListConflictsAction::class)->execute('kb'))->toHaveCount(1);
    });
});

it('lists what agents published lately under review: auto, ready to revert', function () use ($live) {
    config()->set('larecipe.review', 'auto');
    app()->forgetScopedInstances();

    $published = AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nAgent wrote this.\n");

    $recent = $this->getJson('/kb/_admin/api/review')
        ->assertOk()
        ->assertJsonPath('mode', 'auto')
        ->assertJsonPath('drafts', [])
        ->assertJsonPath('recent.0.id', $published->id)
        ->json('recent.0');

    $this->postJson("/kb/_admin/api/revisions/{$recent['replaces']}/revert")->assertOk()->assertJsonPath('revision.channel', 'admin_ui');

    expect($live('billing/refunds')->body)->toBe("# Refunds\n\nMoney back.\n");
    $this->getJson('/kb/_admin/api/review')->assertJsonPath('recent', []);
});
