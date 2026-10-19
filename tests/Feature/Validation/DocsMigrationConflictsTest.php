<?php

use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Tests\Feature\Admin\Support\AdminFixtures;
use LaRecipe\Tests\Feature\Admin\Support\UsesAdminSets;

/*
 * A docs migration that met a production edit lands as a conflict draft and
 * never fails a deploy. larecipe:validate keeps reminding the team until
 * someone resolves it in the admin review queue.
 */

uses(UsesAdminSets::class);

it('reports open docs-migration conflicts on a database set, with where to resolve them', function () {
    AdminFixtures::page('billing/refunds', "# Refunds\n\nMoney back.\n");
    AdminFixtures::agentDraft('billing/refunds', "# Refunds\n\nMoney back within 30 days.\n");

    expect(collect(app(ValidateDocsAction::class)->execute('kb')->issues)->pluck('rule'))->not->toContain('migrations.conflict');

    $conflict = AdminFixtures::conflict('support/escalation');
    $issues = collect(app(ValidateDocsAction::class)->execute('kb')->issues)
        ->where('rule', 'migrations.conflict')
        ->map(fn (ValidationIssue $issue) => $issue->toArray())
        ->values();

    expect($issues)->toHaveCount(1)
        ->and($issues[0])->toMatchArray(['level' => 'warning', 'set' => 'kb', 'page' => 'support/escalation'])
        ->and($issues[0]['message'])->toContain('2026_10_17_141502_docs_pagerduty')
        ->and($issues[0]['fix'])->toContain("/kb/_admin/review/{$conflict->id}")
        ->and($issues[0]['fix'])->toContain('keep production, take the migration, or edit a merge');
});
