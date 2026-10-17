<?php

use LaRecipe\Tests\Feature\Sync\TemporaryProject;

/*
 * The AGENTS.md block is compared with golden files in
 * tests/Fixtures/track-d/golden. Run with LARECIPE_UPDATE_GOLDEN=1 to rewrite
 * them after an intended wording change, then review the diff.
 */

beforeEach(function () {
    $this->project = TemporaryProject::create('app')->init();
    $this->project->commit('Initial app and docs');
    $this->project->mount($this->app);
});

afterEach(fn () => $this->project->destroy());

function assertGolden(string $name, ?string $actual): void
{
    $file = dirname(__DIR__, 2).'/Fixtures/track-d/golden/'.$name;

    if (getenv('LARECIPE_UPDATE_GOLDEN') === '1') {
        @mkdir(dirname($file), 0755, true);
        file_put_contents($file, (string) $actual);
    }

    expect($actual)->toBe(file_get_contents($file));
}

it('writes the instructions block for a repository set', function () {
    $this->artisan('larecipe:agents', ['--no-index' => true])->assertSuccessful();

    assertGolden('agents-repository-required.md', $this->project->read('AGENTS.md'));
});

it('follows the review mode', function () {
    config(['larecipe.review' => 'auto']);
    $this->app->forgetScopedInstances();

    $this->artisan('larecipe:agents', ['--no-index' => true])->assertSuccessful();

    assertGolden('agents-repository-auto.md', $this->project->read('AGENTS.md'));
});

it('detects the default branch from origin/HEAD', function () {
    $this->project->git('update-ref', 'refs/remotes/origin/trunk', 'HEAD');
    $this->project->git('symbolic-ref', 'refs/remotes/origin/HEAD', 'refs/remotes/origin/trunk');

    $this->artisan('larecipe:agents', ['--no-index' => true])->assertSuccessful();

    expect($this->project->read('AGENTS.md'))->toContain('php artisan larecipe:affected --uncommitted --base=trunk');
});

it('tells agents to write docs migrations through the MCP tools for a database set', function () {
    $this->project->mount($this->app, [
        'handbook' => [
            'route' => ['prefix' => 'handbook'],
            'source' => ['driver' => 'database'],
            'access' => ['mode' => 'auth'],
        ],
    ]);
    config(['larecipe.default' => 'handbook']);

    $this->artisan('larecipe:agents', ['--no-index' => true])->assertSuccessful();

    assertGolden('agents-database.md', $this->project->read('AGENTS.md'));
});

it('describes every set when there are several', function () {
    $this->project->mount($this->app, [
        'docs' => [
            'route' => ['prefix' => 'docs'],
            'source' => ['driver' => 'repository', 'path' => $this->project->path('docs')],
        ],
        'handbook' => [
            'route' => ['prefix' => 'handbook'],
            'source' => ['driver' => 'database'],
        ],
    ]);

    $this->artisan('larecipe:agents', ['--no-index' => true])->assertSuccessful();

    assertGolden('agents-mixed.md', $this->project->read('AGENTS.md'));
});

it('adds the docs index to the same block', function () {
    $this->artisan('larecipe:agents-md')->assertSuccessful();

    $agents = (string) $this->project->read('AGENTS.md');

    assertGolden('agents-with-index.md', $agents);
    expect(substr_count($agents, '<!-- larecipe:start -->'))->toBe(1)
        ->and(strlen($agents))->toBeLessThan(10000);
});

it('keeps the index within its byte budget', function () {
    foreach (range(1, 400) as $i) {
        $this->project->write("docs/reference/page-{$i}.md", "---\ntitle: Reference page {$i}\ndescription: ".str_repeat('A long description that keeps going. ', 6)."\n---\n\n# Page {$i}\n");
    }

    $this->artisan('larecipe:agents-md')->assertSuccessful();

    $block = (string) $this->project->read('AGENTS.md');
    $index = substr($block, (int) strpos($block, '### Docs index'));

    expect(strlen($index))->toBeLessThanOrEqual(8192)
        ->and($index)->toContain('more pages: call `list_pages`');
});

it('never touches text outside the markers', function () {
    $before = "# Acme agents guide\n\nRun `composer test` before pushing.\n\n";
    $after = "\n## Deploys\n\nNever deploy on Fridays.\n";
    $this->project->write('AGENTS.md', $before."<!-- larecipe:start -->\nold text\n<!-- larecipe:end -->\n".$after);

    $this->artisan('larecipe:agents', ['--no-index' => true])->assertSuccessful();
    $agents = (string) $this->project->read('AGENTS.md');

    expect($agents)->toStartWith($before.'<!-- larecipe:start -->')
        ->and($agents)->toEndWith("<!-- larecipe:end -->\n".$after)
        ->and($agents)->not->toContain('old text');

    // A second run changes nothing.
    $this->artisan('larecipe:agents', ['--no-index' => true])->assertSuccessful();
    expect($this->project->read('AGENTS.md'))->toBe($agents);
});

it('relies on the Boost guideline when Boost is installed', function () {
    $this->project->write('composer.json', json_encode(['require-dev' => ['laravel/boost' => '^2.10']]));

    $this->artisan('larecipe:agents')
        ->expectsOutputToContain('Laravel Boost is installed')
        ->assertSuccessful();

    expect($this->project->read('AGENTS.md'))->toBeNull();
});

it('adds @AGENTS.md to CLAUDE.md only with consent', function () {
    $this->project->write('CLAUDE.md', "# Claude notes\n\nBe brief.\n");

    $this->artisan('larecipe:agents-md', ['--no-interaction' => true])
        ->expectsOutputToContain('Run again with --claude-md')
        ->assertSuccessful();
    expect($this->project->read('CLAUDE.md'))->toBe("# Claude notes\n\nBe brief.\n");

    $this->artisan('larecipe:agents-md')
        ->expectsConfirmation('CLAUDE.md does not import AGENTS.md, so Claude Code will not read the LaRecipe block. Add @AGENTS.md to CLAUDE.md?', 'no')
        ->assertSuccessful();
    expect($this->project->read('CLAUDE.md'))->toBe("# Claude notes\n\nBe brief.\n");

    $this->artisan('larecipe:agents-md', ['--claude-md' => true])->assertSuccessful();
    expect($this->project->read('CLAUDE.md'))->toBe("# Claude notes\n\nBe brief.\n\n<!-- larecipe:start -->\n@AGENTS.md\n<!-- larecipe:end -->\n");
});

it('asks the agent to refresh the index when pages were added', function () {
    $this->artisan('larecipe:agents-md')->assertSuccessful();
    $this->project->commit('Docs index');
    $this->project->branch('feature');
    $this->project->write('docs/refunds.md', "---\ntitle: Refunds\nsources: [\"app/Billing/**\"]\n---\n\n# Refunds\n");
    $this->project->append('app/Billing/Invoice.php', "// refunds\n")->commit('Add refunds');

    $this->artisan('larecipe:hook', ['event' => 'pre-push', '--payload' => '{"session_id":"s","tool_input":{"command":"git push"}}'])
        ->expectsOutputToContain('run php artisan larecipe:agents-md so the docs index in AGENTS.md stays current')
        ->assertSuccessful();
});
