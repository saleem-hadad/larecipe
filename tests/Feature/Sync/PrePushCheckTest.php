<?php

use Illuminate\Support\Facades\Artisan;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Contracts\ContentSource;
use LaRecipe\Domains\Sources\DTOs\Asset;
use LaRecipe\Facades\LaRecipe;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

beforeEach(function () {
    $this->project = TemporaryProject::create('app')->init();
    $this->project->commit('Initial app and docs');
    $this->project->branch('feature');
    $this->project->mount($this->app);
});

afterEach(function () {
    putenv('LARECIPE_HOOKS');
    $this->project->destroy();
});

function prePush(string $session = 'session-1', string $command = 'git push'): ?array
{
    Artisan::call('larecipe:hook', ['event' => 'pre-push', '--payload' => json_encode([
        'session_id' => $session,
        'hook_event_name' => 'PreToolUse',
        'tool_name' => 'Bash',
        'tool_input' => ['command' => $command],
    ])]);

    $output = trim(Artisan::output());

    return $output === '' ? null : json_decode($output, true);
}

it('holds a push that leaves docs stale, once, with the pages and next steps', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    $decision = prePush();

    expect($decision['hookSpecificOutput']['hookEventName'])->toBe('PreToolUse')
        ->and($decision['hookSpecificOutput']['permissionDecision'])->toBe('deny')
        ->and($decision['hookSpecificOutput']['permissionDecisionReason'])->toBe(implode("\n", [
            'LaRecipe: this push changes code that 1 docs page describes, and it was not updated:',
            '  - docs/billing.md   (sources: app/Billing/**)',
            'Update it with the larecipe-update-docs skill, run php artisan larecipe:validate,',
            'show the user the docs diff and wait for approval, then commit and push again.',
            'If no docs change is needed, tell the user why and push again; the next push is allowed.',
        ]));

    // The loop guard: the same commit and page list pass the second time.
    expect(prePush())->toBeNull();

    // Another session gets its own single block.
    expect(prePush('session-2')['hookSpecificOutput']['permissionDecision'])->toBe('deny');
});

it('blocks again after a new commit that still leaves the page stale', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    expect(prePush())->not->toBeNull();
    expect(prePush())->toBeNull();

    $this->project->append('app/Billing/Invoice.php', "// more\n")->commit('More tax');
    expect(prePush())->not->toBeNull();
});

it('allows a push whose docs were updated, committed or not', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    $this->project->append('docs/billing.md', "\nTaxes apply per line.\n");

    expect(prePush())->toBeNull();
});

it('ignores commands that do not push, even when called without the bin', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    expect(prePush(command: 'php artisan test'))->toBeNull()
        ->and(prePush(command: 'git push origin feature'))->not->toBeNull();
});

it('allows a push that touches no documented code', function () {
    $this->project->write('app/Console/Kernel.php', "<?php\n")->commit('Unrelated');

    expect(prePush())->toBeNull();
});

it('allows everything when LARECIPE_HOOKS is off', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');
    putenv('LARECIPE_HOOKS=off');

    expect(prePush())->toBeNull();
});

it('tells the agent to push without asking in auto review mode', function () {
    config(['larecipe.review' => 'auto']);
    $this->app->forgetScopedInstances();
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    $reason = prePush()['hookSpecificOutput']['permissionDecisionReason'];

    expect($reason)->toContain("run php artisan larecipe:validate,\nthen commit and push again.")
        ->and($reason)->not->toContain('wait for approval');
});

it('counts the branch docs migration as the update for a database set', function () {
    registerFakeDatabaseSet($this->project, $this->app);
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    $reason = prePush()['hookSpecificOutput']['permissionDecisionReason'];
    expect($reason)->toContain('handbook:billing')
        ->and($reason)->toContain('database pages through the LaRecipe MCP tools, which write the branch\'s docs migration in database/migrations/docs/');

    $this->project->write('database/migrations/docs/2026_10_17_120000_docs_feature.php', <<<'PHP'
        <?php

        use LaRecipe\Migrations\DocsMigration;

        return new class extends DocsMigration
        {
            public function up(): void
            {
                $this->updatePage('billing', basedOn: 'sha256:abc', frontmatter: ['title' => 'Billing'], body: 'Taxes apply.');
            }
        };
        PHP);

    expect(prePush('session-3'))->toBeNull();
});

it('injects a compact docs map at session start', function () {
    $this->project->append('app/Billing/Invoice.php', "// drift\n")->commit('Change billing without docs');

    Artisan::call('larecipe:hook', ['event' => 'session-start']);
    $output = json_decode(trim(Artisan::output()), true);
    $context = $output['hookSpecificOutput']['additionalContext'];

    expect($output['hookSpecificOutput']['hookEventName'])->toBe('SessionStart')
        ->and($context)->toContain('LaRecipe docs: set `docs` in `docs/` (repository, 3 pages, review mode required).')
        ->and($context)->toContain('Map: billing, index, guides/{webhooks}')
        ->and($context)->toContain('Stale: 1 page documents code that changed after the page was last updated')
        ->and($context)->toContain('php artisan larecipe:affected --uncommitted --base=main')
        ->and(strlen($context))->toBeLessThan(1600);
});

it('warns from the git pre-push hook and blocks only when strict', function () {
    $this->project->append('app/Billing/Invoice.php', "// tax\n")->commit('Add tax');

    $this->artisan('larecipe:hook', ['event' => 'git-pre-push'])
        ->expectsOutputToContain('docs/billing.md')
        ->assertSuccessful();

    $this->artisan('larecipe:hook', ['event' => 'git-pre-push', '--strict' => true])
        ->expectsOutputToContain('LaRecipe held this push.')
        ->assertFailed();
});

/**
 * A database set whose single page documents app/Billing/**, standing in for
 * the Sources track's database driver.
 */
function registerFakeDatabaseSet(TemporaryProject $project, $app): void
{
    $project->mount($app, [
        'handbook' => [
            'route' => ['prefix' => 'handbook', 'middleware' => ['web']],
            'source' => ['driver' => 'database'],
            'access' => ['mode' => 'public'],
        ],
    ]);
    config(['larecipe.default' => 'handbook']);
    $app->forgetScopedInstances();

    LaRecipe::extendSources('database', fn () => new class implements ContentSource
    {
        public function manifest(): ?Manifest
        {
            return Manifest::fromArray(['name' => 'Handbook']);
        }

        public function pages(string $version, string $locale): iterable
        {
            return [new PageRef('handbook', $version, $locale, 'billing')];
        }

        public function page(PageRef $ref): ?Page
        {
            return Page::make($ref, new Frontmatter(['title' => 'Billing', 'sources' => ['app/Billing/**']]), "# Billing\n", new DateTimeImmutable('-1 day'));
        }

        public function navigation(string $version, string $locale): ?NavigationTree
        {
            return null;
        }

        public function asset(string $version, string $path): ?Asset
        {
            return null;
        }

        public function fingerprint(?string $version = null): string
        {
            return 'fake';
        }
    });
}
