<?php

use Illuminate\Support\Facades\Artisan;
use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\SchemaValidator;
use LaRecipe\Tests\Feature\Agents\Support\Sets;
use LaRecipe\Tests\TestCase;

function useBrokenSet(array $set = [], array $config = []): void
{
    Sets::use([
        'docs' => [
            'route' => ['prefix' => 'docs', 'middleware' => ['web']],
            'source' => ['driver' => 'repository', 'path' => TestCase::fixture('docs')],
        ],
        'broken' => array_replace_recursive([
            'route' => ['prefix' => 'broken', 'middleware' => ['web']],
            'source' => ['driver' => 'repository', 'path' => Sets::fixture('broken')],
            'snippets' => ['root' => Sets::fixture('code')],
        ], $set),
    ], $config);
}

/** @return array<string, list<array>> issues by rule */
function brokenIssues(): array
{
    $report = app(ValidateDocsAction::class)->execute('broken');

    return collect($report->issues)->map(fn (ValidationIssue $issue) => $issue->toArray())->groupBy('rule')->map->all()->all();
}

beforeEach(fn () => useBrokenSet());

it('finds broken links, anchors and absolute links, with fixes', function () {
    $links = collect(brokenIssues()['links.broken'])->keyBy('message');

    expect($links->keys()->all())->toContain(
        'Link to guide.md#setup: anchor #setup not found.',
        'Link to nowhere.md: there is no page at "nowhere".',
        'Link to #the-end: there is no such anchor on this page.',
        'Link to /broken/guide#nope: anchor #nope not found.',
    );

    $anchor = $links['Link to guide.md#setup: anchor #setup not found.'];

    expect($anchor)->toMatchArray(['level' => 'error', 'set' => 'broken', 'page' => 'index', 'file' => 'index.md', 'line' => 9])
        ->and($links['Link to nowhere.md: there is no page at "nowhere".']['fix'])->toBe('Fix the link, or write the page it points to.');
});

it('warns when a public page links to a private one', function () {
    $issue = brokenIssues()['links.private-from-public'][0];

    expect($issue['level'])->toBe('warning')->and($issue['message'])->toContain('"private"');
});

it('checks navigation entries, orphans and the navigation schema', function () {
    $issues = brokenIssues();
    $missing = collect($issues['navigation.missing-page'])->pluck('fix', 'message');

    expect($missing->keys()->all())->toContain('The navigation lists "privat", but there is no such page.', 'The navigation lists "missing-page", but there is no such page.')
        ->and($missing['The navigation lists "privat", but there is no such page.'])->toBe('Change the entry to "private".');

    expect(collect($issues['pages.orphan'])->pluck('page')->all())->toContain('orphan')->not->toContain('foo')
        ->and($issues['navigation.schema'][0]['message'])->toContain('collapsed');
});

it('checks the manifest against its schema and catches typos in keys', function () {
    $issues = collect(brokenIssues()['manifest.schema']);

    expect($issues->firstWhere('level', 'error')['message'])->toContain('/theme/radius')
        ->and($issues->firstWhere('level', 'warning')['fix'])->toBe('Rename "descripton" to "description".');
});

it('checks frontmatter: YAML, schema and typos', function () {
    $issues = collect(brokenIssues()['frontmatter.schema']);

    expect($issues->firstWhere('page', 'bad-yaml')['message'])->toStartWith('The frontmatter is not valid YAML')
        ->and($issues->firstWhere(fn ($i) => str_contains($i['message'], '/layout'))['level'])->toBe('error')
        ->and($issues->firstWhere('fix', 'Rename "descripton" to "description".')['page'])->toBe('index');
});

it('reports unknown components with the closest known name', function () {
    $issue = brokenIssues()['components.unknown'][0];

    expect($issue)->toMatchArray(['level' => 'error', 'message' => 'Unknown component <Calout>.', 'fix' => 'Use <Callout>.', 'line' => 12]);
});

it('reports video placeholders but not examples inside code blocks', function () {
    $issues = brokenIssues()['media.placeholder'];

    expect($issues)->toHaveCount(1)
        ->and($issues[0])->toMatchArray(['level' => 'warning', 'line' => 16])
        ->and($issues[0]['message'])->toContain('Demo: the product in two minutes');
});

it('reports missing snippet files and regions, never reading outside the snippet root', function () {
    $messages = collect(brokenIssues()['snippets.missing'])->pluck('message')->all();

    expect($messages)->toContain('The region "missing-region" is not in @/Invoice.php.', 'The included file @/Gone.php does not exist.')
        ->not->toContain('The region "refund" is not in @/Invoice.php.');
});

it('reports files that collide on one page, redirect loops and shadowed redirects', function () {
    $issues = brokenIssues();

    expect($issues['pages.duplicate'][0]['message'])->toContain('foo.md')->toContain('foo/index.md')
        ->and($issues['redirects.loop'][0]['message'])->toBe('These redirects form a loop: /broken/old -> /broken/older -> /broken/old.')
        ->and(collect($issues['redirects.shadowed'])->pluck('message')->implode(' '))->toContain('/broken/guide never runs');
});

it('checks hand-written skills against the Agent Skills spec', function () {
    $messages = collect(brokenIssues()['skills.spec'])->pluck('message')->all();

    expect($messages)->toContain('The skill name "Bad_Name" is not valid: use 1 to 64 lowercase letters, digits and single hyphens.')
        ->toContain('The frontmatter key "argument-hint" is not part of the Agent Skills spec, so some agents will refuse the skill.');
});

it('warns about budgets: agent Markdown size and the search index', function () {
    useBrokenSet(['agents' => ['markdown_budget' => 100, 'search_budget' => 50]]);

    $issues = brokenIssues();

    expect($issues['markdown.size'][0]['level'])->toBe('warning')
        ->and($issues['search.budget'][0]['message'])->toContain('would be about');
});

it('fails a database set on a cache store that keeps nothing', function () {
    useBrokenSet(['source' => ['driver' => 'database']], ['app.env' => 'production']);
    app(SourceManager::class)->extend('database', fn ($app, $set) => new RepositorySource($set->key, Sets::fixture('broken')));
    app()->detectEnvironment(fn () => 'production');

    expect(brokenIssues()['cache.store'][0]['message'])->toContain('"array" cache store');
});

it('passes clean docs with warnings only', function () {
    $report = app(ValidateDocsAction::class)->execute('docs');

    expect($report->hasErrors())->toBeFalse()->and($report->pages)->toBe(5);
});

it('prints a JSON report that matches validate.schema.json and exits non-zero on errors', function () {
    $this->artisan('larecipe:validate', ['--set' => 'broken', '--json' => true])->assertExitCode(1);

    Artisan::call('larecipe:validate', ['--set' => 'broken', '--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($report['ok'])->toBeFalse()
        ->and($report['summary']['errors'])->toBeGreaterThan(10)
        ->and(app(SchemaValidator::class)->errors(SchemaValidator::REPORT, $report))->toBe([]);

    $this->artisan('larecipe:validate', ['--set' => 'docs'])->assertExitCode(0)->expectsOutputToContain('Checked 5 pages');
    $this->artisan('larecipe:validate', ['--set' => 'nope'])->assertExitCode(2);
    $this->artisan('larecipe:validate', ['--set' => 'docs', '--docs-version' => '9.x'])->assertExitCode(2);
});

it('prints readable issues with their fixes', function () {
    $this->artisan('larecipe:validate', ['--set' => 'broken'])
        ->expectsOutputToContain('links.broken')
        ->expectsOutputToContain('Fix: Use <Callout>.')
        ->assertExitCode(1);
});

it('checks one page before a write, with link problems as warnings', function () {
    $page = Page::make(
        new PageRef('broken', 'current', 'en', 'new-page'),
        new Frontmatter(['title' => 'New page', 'titel' => 'typo']),
        "# New page\n\nSee [the next page](next-page.md) and [the guide](guide.md#install).\n\n<Steeps>\nOne\n</Steeps>\n",
    );

    $report = app(ValidatePageAction::class)->execute($page);
    $rules = collect($report->issues)->map(fn (ValidationIssue $i) => $i->rule.':'.$i->level)->all();

    expect($report->hasErrors())->toBeTrue()
        ->and($rules)->toContain('components.unknown:error', 'links.broken:warning', 'frontmatter.schema:warning', 'frontmatter.description.missing:warning')
        ->and(collect($report->issues)->where('rule', 'links.broken')->count())->toBe(1);

    $empty = Page::make(new PageRef('nowhere', 'current', 'en', 'x'), new Frontmatter(['description' => 'Nothing here.']), '');

    expect(app(ValidatePageAction::class)->execute($empty)->issues[0]->rule)->toBe('page.empty');
});
