<?php

use LaRecipe\Tests\Feature\Onboarding\FakeAgents;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

beforeEach(function () {
    $this->project = TemporaryProject::create('fresh')->init();
    $this->project->write('docs/index.md', "---\ntitle: Introduction\ndescription: Acme Invoices.\n---\n\n# Acme Invoices\n")->commit('Initial');
    $this->project->mount($this->app);
    $this->agents = (new FakeAgents(['claude', 'codex']))->detectIn($this->app);
});

afterEach(function () {
    $this->agents->destroy();
    $this->project->destroy();
});

const CLAUDE_COMMAND = 'claude -p "Use the larecipe-write-docs skill to run onboarding for audiences: api, internal (set: docs)" --allowedTools "Read,Grep,Glob,Edit(docs/**),Write(docs/**),Bash(php artisan larecipe:*)" --output-format stream-json --verbose';

it('shows the exact command and only prints it with --print', function () {
    $this->artisan('larecipe:onboard', ['--audience' => 'api,internal', '--agent' => 'claude', '--print' => true])
        ->expectsOutputToContain(CLAUDE_COMMAND)
        ->expectsOutputToContain('write only inside docs/')
        ->assertSuccessful();

    expect($this->project->read('claude-args.txt'))->toBeNull();
});

it('asks before running, streams progress, validates and lists the pages', function () {
    $this->artisan('larecipe:onboard', ['--audience' => 'api,internal', '--agent' => 'claude'])
        ->expectsOutputToContain(CLAUDE_COMMAND)
        ->expectsConfirmation('Run it now?', 'yes')
        ->expectsOutputToContain('Reading the API routes.')
        ->expectsOutputToContain('> Write docs/api/authentication.md')
        ->expectsOutputToContain('Pages written for your review (not committed):')
        ->expectsOutputToContain('docs/api/authentication.md')
        ->expectsOutputToContain('The new pages pass validation.')
        ->assertSuccessful();

    expect($this->project->read('claude-args.txt'))->toBe(implode("\n", [
        '-p',
        'Use the larecipe-write-docs skill to run onboarding for audiences: api, internal (set: docs)',
        '--allowedTools',
        'Read,Grep,Glob,Edit(docs/**),Write(docs/**),Bash(php artisan larecipe:*)',
        '--output-format',
        'stream-json',
        '--verbose',
    ])."\n")
        ->and($this->project->read('.claude/skills/larecipe-write-docs/references/onboarding.md'))->not->toBeNull()
        ->and($this->project->git('status', '--porcelain', '--untracked-files=all'))->toContain('?? docs/api/authentication.md')
        ->and($this->project->git('rev-list', '--count', 'HEAD'))->toBe('1');
});

it('does not run without consent', function () {
    $this->artisan('larecipe:onboard', ['--audience' => 'api', '--agent' => 'claude'])
        ->expectsConfirmation('Run it now?', 'no')
        ->assertSuccessful();

    $this->artisan('larecipe:onboard', ['--audience' => 'api', '--agent' => 'claude', '--no-interaction' => true])
        ->expectsOutputToContain('Run again with --yes')
        ->assertSuccessful();

    expect($this->project->read('docs/api/authentication.md'))->toBeNull();
});

it('runs with --yes', function () {
    $this->artisan('larecipe:onboard', ['--audience' => 'api', '--agent' => 'claude', '--yes' => true, '--no-interaction' => true])
        ->assertSuccessful();

    expect($this->project->read('docs/api/authentication.md'))->toContain('title: Authentication');
});

it('builds the codex exec equivalent', function () {
    $this->artisan('larecipe:onboard', ['--audience' => 'package', '--agent' => 'codex', '--print' => true])
        ->expectsOutputToContain("codex exec --sandbox workspace-write 'Use the \$larecipe-write-docs skill to run onboarding for audiences: package (set: docs)'")
        ->expectsOutputToContain('cannot limit writes to one folder')
        ->assertSuccessful();
});

it('scopes a database set to the LaRecipe MCP tools', function () {
    $this->project->mount($this->app, ['docs' => ['route' => ['prefix' => 'docs'], 'source' => ['driver' => 'database']]]);

    $this->artisan('larecipe:onboard', ['--audience' => 'internal', '--agent' => 'claude', '--print' => true])
        ->expectsOutputToContain('--allowedTools "Read,Grep,Glob,mcp__larecipe,Bash(php artisan larecipe:*)"')
        ->assertSuccessful();

    expect($this->project->read('.mcp.json'))->toContain('"larecipe"');
});

it('prints the prompt for agents it cannot run headless', function () {
    $this->artisan('larecipe:onboard', ['--audience' => 'product', '--agent' => 'cursor'])
        ->expectsOutputToContain('Use the larecipe-write-docs skill to run onboarding for audiences: product (set: docs)')
        ->expectsOutputToContain('LaRecipe does not run Cursor headless yet')
        ->assertSuccessful();
});

it('keeps the scaffold and prints the command for later without an agent', function () {
    (new FakeAgents([]))->detectIn($this->app);

    $this->artisan('larecipe:onboard', ['--audience' => 'api'])
        ->expectsOutputToContain('No local coding agent found')
        ->expectsOutputToContain('php artisan larecipe:onboard --audience=api --set=docs')
        ->assertSuccessful();
});

it('picks the detected agent and asks which audiences', function () {
    $this->artisan('larecipe:onboard', ['--print' => true])
        ->expectsQuestion('Who are these docs for?', ['api'])
        ->expectsChoice('Which agent should write the docs?', 'claude', ['claude' => 'Claude Code', 'codex' => 'Codex'])
        ->expectsOutputToContain('onboarding for audiences: api (set: docs)')
        ->assertSuccessful();
});
