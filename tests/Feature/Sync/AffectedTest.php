<?php

use LaRecipe\Domains\Sync\Actions\FindAffectedPagesAction;
use LaRecipe\Domains\Sync\DTOs\AffectedPage;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

beforeEach(function () {
    $this->project = TemporaryProject::create('app')->init();
    $this->project->commit('Initial app and docs');
    $this->project->branch('feature');
    $this->project->mount($this->app);
});

afterEach(fn () => $this->project->destroy());

function affectedPaths(array $pages): array
{
    return array_map(fn (AffectedPage $page) => $page->page->ref->path.':'.$page->status->value, $pages);
}

it('maps committed changes to the pages whose sources match', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    $report = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(base: 'main'));

    expect(affectedPaths($report->pages))->toBe(['billing:stale'])
        ->and($report->pages[0]->changedFiles)->toBe(['app/Billing/Invoice.php'])
        ->and($report->pages[0]->matchedSources)->toBe(['app/Billing/**'])
        ->and($report->changedFiles)->toBe(['app/Billing/Invoice.php']);
});

it('counts a page as updated when the same range changes it', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")
        ->append('docs/billing.md', "\nTaxes are added per line.\n")
        ->commit('Add tax and document it');

    $report = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(base: 'main'));

    expect(affectedPaths($report->pages))->toBe(['billing:updated'])
        ->and($report->hasStale())->toBeFalse();
});

it('includes uncommitted work, staged or not, and new files', function () {
    $this->project->write('app/Billing/Refund.php', "<?php\n");

    $report = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(base: 'main', uncommitted: true));
    expect(affectedPaths($report->pages))->toBe(['billing:stale']);

    $committedOnly = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(base: 'main'));
    expect($committedOnly->pages)->toBe([]);
});

it('only counts a region source when the change touches that region', function () {
    $handler = $this->project->read('app/Webhooks/Handler.php');

    $this->project->write('app/Webhooks/Handler.php', str_replace('//'."\n    }", '// log it'."\n    }", $handler))->commit('Touch log only');
    $outside = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(base: 'main'));
    expect($outside->pages)->toBe([]);

    $this->project->write('app/Webhooks/Handler.php', str_replace("return \$signature !== '';", "return hash_equals('x', \$signature);", (string) $this->project->read('app/Webhooks/Handler.php')))->commit('Harden verify');
    $inside = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(base: 'main'));
    expect(affectedPaths($inside->pages))->toBe(['guides/webhooks:stale']);
});

it('reports pages that drifted before the current change', function () {
    $this->project->append('app/Billing/Invoice.php', "// drift\n")->commit('Change billing without docs');

    $report = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(uncommitted: true));

    expect($report->pages)->toBe([])
        ->and($report->drift)->toHaveCount(1)
        ->and($report->drift[0]->page->ref->path)->toBe('billing')
        ->and($report->drift[0]->staleSince->subject)->toBe('Change billing without docs');
});

it('finds translations that fell behind their source page', function () {
    $this->project->append('docs/billing.md', "\nNew paragraph.\n")->commit('Update English page');

    $report = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(base: 'main', locale: 'ar'));

    expect(affectedPaths($report->pages))->toBe(['billing:stale'])
        ->and($report->pages[0]->page->ref->locale)->toBe('ar')
        ->and($report->pages[0]->reason->value)->toBe('translation');

    $later = app(FindAffectedPagesAction::class)->execute(new AffectedQuery(uncommitted: true, locale: 'ar'));

    expect($later->pages)->toBe([])
        ->and($later->drift)->toHaveCount(1)
        ->and($later->drift[0]->reason->value)->toBe('translation')
        ->and($later->drift[0]->staleSince->subject)->toBe('Update English page');
});

it('prints json, text and github formats, and fails on stale pages when asked', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    $this->artisan('larecipe:affected', ['--base' => 'main', '--format' => 'text'])
        ->expectsOutputToContain('Stale (the code changed, the page did not):')
        ->expectsOutputToContain('docs/billing.md   (sources: app/Billing/**)')
        ->assertSuccessful();

    $this->artisan('larecipe:affected', ['--base' => 'main', '--format' => 'github', '--fail-on-stale' => true])
        ->expectsOutputToContain('::error file=docs/billing.md,title=Stale docs page: docs/billing.md::')
        ->assertFailed();

    $this->artisan('larecipe:affected', ['--base' => 'main', '--format' => 'json', '--no-drift' => true])
        ->expectsOutputToContain('"stale": 1')
        ->assertSuccessful();
});

it('explains itself outside a git repository', function () {
    $plain = TemporaryProject::create('app');
    $plain->mount($this->app);

    $this->artisan('larecipe:affected')
        ->expectsOutputToContain('not inside a git repository')
        ->assertSuccessful();

    $plain->destroy();
});
