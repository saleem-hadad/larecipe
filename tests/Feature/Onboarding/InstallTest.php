<?php

use LaRecipe\Domains\Onboarding\DTOs\AppFacts;
use LaRecipe\Domains\Onboarding\Services\IntroductionPage;
use LaRecipe\Tests\Feature\Onboarding\FakeAgents;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

/*
 * Written configs are compared with golden files in
 * tests/Fixtures/track-d/golden/install. Run with LARECIPE_UPDATE_GOLDEN=1 to
 * rewrite them after an intended change, then review the diff.
 */

beforeEach(function () {
    $this->project = TemporaryProject::create('fresh')->init();
    $this->project->commit('Fresh app');
    $this->project->mount($this->app);
    config(['app.name' => 'Acme Invoices']);
    $this->agents = (new FakeAgents(['claude', 'codex']))->detectIn($this->app);
});

afterEach(function () {
    $this->agents->destroy();
    $this->project->destroy();
});

function installGolden(TemporaryProject $project, string $file, string $golden): void
{
    $path = dirname(__DIR__, 2).'/Fixtures/track-d/golden/install/'.$golden;

    if (getenv('LARECIPE_UPDATE_GOLDEN') === '1') {
        @mkdir(dirname($path), 0755, true);
        file_put_contents($path, (string) $project->read($file));
    }

    expect($project->read($file))->toBe(file_get_contents($path), "{$file} differs from golden/install/{$golden}");
}

const FULL_INSTALL = [
    '--agents' => 'claude,codex,cursor,copilot,gemini',
    '--git-hook' => 'warn',
    '--ci' => 'fail',
    '--composer-script' => true,
    '--no-interaction' => true,
];

it('sets up the docs and every detected agent, matching the golden files', function () {
    $this->artisan('larecipe:install', FULL_INSTALL)->assertSuccessful();

    installGolden($this->project, 'docs/larecipe.json', 'larecipe.json');
    installGolden($this->project, 'AGENTS.md', 'AGENTS.md');
    installGolden($this->project, '.mcp.json', 'mcp.json');
    installGolden($this->project, '.cursor/mcp.json', 'cursor-mcp.json');
    installGolden($this->project, '.vscode/mcp.json', 'vscode-mcp.json');
    installGolden($this->project, '.gemini/settings.json', 'gemini-settings.json');
    installGolden($this->project, '.codex/config.toml', 'codex-config.toml');
    installGolden($this->project, '.claude/settings.json', 'claude-settings.json');
    installGolden($this->project, '.codex/hooks.json', 'codex-hooks.json');
    installGolden($this->project, '.github/workflows/larecipe-docs-check.yml', 'docs-check-fail.yml');
    installGolden($this->project, 'composer.json', 'composer.json');

    expect($this->project->read('.claude/skills/larecipe-update-docs/SKILL.md'))->toContain('name: larecipe-update-docs')
        ->and($this->project->read('.agents/skills/larecipe-write-docs/references/onboarding.md'))->toContain('## API consumers')
        ->and($this->project->read('bootstrap/cache/larecipe/hooks.php'))->toContain("'git push'")
        ->and($this->project->read('.git/hooks/pre-push'))->toContain('larecipe:hook git-pre-push')
        ->and($this->project->read('config/larecipe.php'))->toBeNull();
});

it('does not call a fresh skeleton "Laravel" a Laravel application', function () {
    $page = app(IntroductionPage::class)->render(new AppFacts(
        name: 'Laravel', laravelVersion: '13.37.0', php: '^8.3', packages: [], readmeIntro: null, description: null,
    ));

    expect($page)->toContain('This is a Laravel 13 application (PHP ^8.3).');
});

it('writes a real Introduction page from facts only', function () {
    $this->artisan('larecipe:install', ['--agents' => 'none', '--no-interaction' => true])->assertSuccessful();

    $intro = (string) $this->project->read('docs/index.md');
    $major = explode('.', $this->app->version())[0];

    expect($intro)->toStartWith("---\ntitle: Introduction\ndescription: 'Acme Invoices sends invoices for small teams and chases the ones that are late.'\nsources: [\"README.md\", \"composer.json\"]\n---\n\n# Acme Invoices\n")
        ->and($intro)->toContain('It connects to Stripe and keeps every total in sync.')
        ->and($intro)->toContain("Acme Invoices is a Laravel {$major} application (PHP ^8.3).")
        ->and($intro)->toContain("- `laravel/sanctum`\n- `livewire/livewire`")
        ->and($intro)->not->toContain('laravel/tinker')
        ->and($intro)->not->toContain('badge.svg')
        ->and($intro)->not->toContain('Local setup');
});

it('is idempotent: a second run changes nothing', function () {
    $this->artisan('larecipe:install', FULL_INSTALL)->assertSuccessful();
    $this->project->commit('Install LaRecipe');

    $this->artisan('larecipe:install', FULL_INSTALL)->assertSuccessful();

    expect($this->project->git('status', '--porcelain', '--untracked-files=all'))->toBe('');
});

it('writes nothing on a dry run', function () {
    $this->artisan('larecipe:install', [...FULL_INSTALL, '--dry-run' => true])
        ->expectsOutputToContain('Dry run: nothing was written.')
        ->expectsOutputToContain('would be created')
        ->assertSuccessful();

    expect($this->project->git('status', '--porcelain', '--untracked-files=all'))->toBe('')
        ->and($this->project->read('.git/hooks/pre-push'))->toBeNull();
});

it('merges into settings the team already has and never replaces them', function () {
    $this->project->write('.claude/settings.json', json_encode([
        'permissions' => ['allow' => ['Bash(composer test)']],
        'hooks' => ['PreToolUse' => [['matcher' => 'Edit', 'hooks' => [['type' => 'command', 'command' => 'vendor/bin/pint --dirty']]]]],
    ]));
    $this->project->write('.mcp.json', json_encode(['mcpServers' => ['laravel-boost' => ['command' => 'php', 'args' => ['artisan', 'boost:mcp']]]]));
    $this->project->write('.cursor/mcp.json', json_encode(['mcpServers' => ['larecipe' => ['command' => 'php', 'args' => ['other/artisan', 'larecipe:mcp']]]]));
    $this->project->write('.codex/config.toml', "model = \"gpt-5\"\n\n[mcp_servers.github]\ncommand = \"github-mcp\"\n");
    $this->project->write('docs/larecipe.json', "{\"name\": \"Our own name\"}\n");

    $this->artisan('larecipe:install', ['--agents' => 'claude,codex,cursor', '--no-interaction' => true])->assertSuccessful();

    $claude = json_decode((string) $this->project->read('.claude/settings.json'), true);

    expect($claude['permissions'])->toBe(['allow' => ['Bash(composer test)']])
        ->and($claude['hooks']['PreToolUse'][0]['hooks'][0]['command'])->toBe('vendor/bin/pint --dirty')
        ->and($claude['hooks']['PreToolUse'][1]['hooks'][0]['command'])->toBe('"$CLAUDE_PROJECT_DIR"/vendor/bin/larecipe-hook pre-push')
        ->and(json_decode((string) $this->project->read('.mcp.json'), true)['mcpServers'])->toHaveKeys(['laravel-boost', 'larecipe'])
        ->and(json_decode((string) $this->project->read('.cursor/mcp.json'), true)['mcpServers']['larecipe']['args'])->toBe(['other/artisan', 'larecipe:mcp'])
        ->and($this->project->read('.codex/config.toml'))->toBe("model = \"gpt-5\"\n\n[mcp_servers.github]\ncommand = \"github-mcp\"\n\n[mcp_servers.larecipe]\ncommand = \"php\"\nargs = [\"artisan\", \"larecipe:mcp\"]\n")
        ->and($this->project->read('docs/larecipe.json'))->toBe("{\"name\": \"Our own name\"}\n");
});

it('records auto review mode in the published config and the instructions', function () {
    $this->artisan('larecipe:install', ['--agents' => 'none', '--review' => 'auto', '--no-interaction' => true])->assertSuccessful();

    expect($this->project->read('config/larecipe.php'))->toContain("'review' => env('LARECIPE_REVIEW', 'auto'),")
        ->and($this->project->read('AGENTS.md'))->toContain('Review mode is `auto`: update the docs and push without asking.');
});

it('configures a database set without scaffolding files', function () {
    $this->artisan('larecipe:install', ['--agents' => 'claude', '--driver' => 'database', '--no-interaction' => true])
        ->assertSuccessful();

    expect($this->project->read('config/larecipe.php'))->toContain("'driver' => 'database',")
        ->and($this->project->read('docs/index.md'))->toBeNull()
        ->and($this->project->read('AGENTS.md'))->toContain('they write a docs migration in `database/migrations/docs/`');
});

it('relies on Boost for the guideline and skills when it is installed', function () {
    $this->project->write('composer.json', json_encode(['require-dev' => ['laravel/boost' => '^2.10']]));

    $this->artisan('larecipe:install', ['--agents' => 'claude', '--no-interaction' => true])
        ->expectsOutputToContain('Laravel Boost is installed')
        ->assertSuccessful();

    expect($this->project->read('AGENTS.md'))->toBeNull()
        ->and($this->project->read('.claude/skills/larecipe-write-docs/SKILL.md'))->toBeNull()
        ->and($this->project->read('.mcp.json'))->toContain('larecipe:mcp');
});

it('asks before adding @AGENTS.md to CLAUDE.md', function () {
    $this->project->write('CLAUDE.md', "# Notes\n");

    $this->artisan('larecipe:install', ['--agents' => 'claude', '--no-interaction' => true])
        ->expectsOutputToContain('larecipe:agents-md --claude-md')
        ->assertSuccessful();
    expect($this->project->read('CLAUDE.md'))->toBe("# Notes\n");

    $this->artisan('larecipe:install', ['--agents' => 'claude', '--claude-md' => true, '--no-interaction' => true])->assertSuccessful();
    expect($this->project->read('CLAUDE.md'))->toBe("# Notes\n\n<!-- larecipe:start -->\n@AGENTS.md\n<!-- larecipe:end -->\n");
});

it('writes the comment-mode CI check', function () {
    $this->artisan('larecipe:install', ['--agents' => 'none', '--ci' => 'comment', '--no-interaction' => true])->assertSuccessful();

    installGolden($this->project, '.github/workflows/larecipe-docs-check.yml', 'docs-check-comment.yml');
});

it('detects agents and walks through the choices interactively', function () {
    $this->artisan('larecipe:install')
        ->expectsChoice('Where should the docs live?', 'repository', [
            'repository' => 'In this repository, as Markdown in docs/ (recommended)',
            'database' => 'In the database, with an admin UI (changes in a pull request ship as docs migrations)',
        ])
        ->expectsQuestion('Which coding agents should LaRecipe set up? (MCP server, hooks)', ['claude'])
        ->expectsConfirmation('Hold an agent\'s push once when it leaves docs pages stale? (pre-push hook, no AI)', 'yes')
        ->expectsQuestion('Also check pushes people make without an agent? (git pre-push hook)', 'none')
        ->expectsQuestion('Add the optional no-AI docs check for pull requests?', 'none')
        ->expectsQuestion('Review mode for docs changes', 'required')
        ->expectsConfirmation('Refresh the agent instructions and skills after every composer update? (adds larecipe:agents to post-update-cmd)', 'no')
        ->expectsConfirmation('Generate starter docs from your app with your coding agent now?', 'no')
        ->assertSuccessful();

    expect($this->project->read('.claude/settings.json'))->toContain('larecipe-hook pre-push')
        ->and($this->project->read('.codex/hooks.json'))->toBeNull();
});

it('hands over to onboarding last, printing the command when it cannot ask', function () {
    $this->artisan('larecipe:install', ['--agents' => 'claude', '--onboard' => 'api', '--no-interaction' => true])
        ->expectsOutputToContain('claude -p "Use the larecipe-write-docs skill to run onboarding for audiences: api (set: docs)"')
        ->expectsOutputToContain('Run again with --yes')
        ->assertSuccessful();
});
