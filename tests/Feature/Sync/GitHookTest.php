<?php

use LaRecipe\Tests\Feature\Sync\TemporaryProject;

beforeEach(function () {
    $this->project = TemporaryProject::create('app')->init();
    $this->project->mount($this->app);
});

afterEach(fn () => $this->project->destroy());

it('installs a warn-only pre-push hook, idempotently', function () {
    $this->artisan('larecipe:hooks:git', ['--install' => true])->assertSuccessful();

    $hook = $this->project->read('.git/hooks/pre-push');

    expect($hook)->toStartWith("#!/bin/sh\n\n# larecipe:start\n")
        ->and($hook)->toContain('(cd "$(git rev-parse --show-toplevel)" && php artisan larecipe:hook git-pre-push) || true')
        ->and($hook)->toContain('never calls an AI')
        ->and(is_executable($this->project->path('.git/hooks/pre-push')))->toBeTrue();

    $this->artisan('larecipe:hooks:git', ['--install' => true])->expectsOutputToContain('unchanged');
    expect($this->project->read('.git/hooks/pre-push'))->toBe($hook);
});

it('blocks with --strict', function () {
    $this->artisan('larecipe:hooks:git', ['--install' => true, '--strict' => true])->assertSuccessful();

    expect($this->project->read('.git/hooks/pre-push'))->toContain('php artisan larecipe:hook git-pre-push --strict) || exit 1');
});

it('respects core.hooksPath', function () {
    $this->project->git('config', 'core.hooksPath', '.githooks');

    $this->artisan('larecipe:hooks:git', ['--install' => true])->assertSuccessful();

    expect($this->project->read('.githooks/pre-push'))->toContain('larecipe:hook git-pre-push')
        ->and($this->project->read('.git/hooks/pre-push'))->toBeNull();
});

it('adds its block to an existing hook and removes only its block', function () {
    $existing = "#!/usr/bin/env bash\nset -e\nvendor/bin/pint --test\n";
    $this->project->write('.git/hooks/pre-push', $existing);

    $this->artisan('larecipe:hooks:git', ['--install' => true])->assertSuccessful();
    $hook = $this->project->read('.git/hooks/pre-push');

    expect($hook)->toStartWith($existing."\n# larecipe:start")
        ->and($hook)->toContain('larecipe:hook git-pre-push');

    $this->artisan('larecipe:hooks:git', ['--uninstall' => true])->assertSuccessful();
    expect($this->project->read('.git/hooks/pre-push'))->toBe($existing);
});

it('deletes a hook it created on uninstall', function () {
    $this->artisan('larecipe:hooks:git', ['--install' => true]);
    $this->artisan('larecipe:hooks:git', ['--uninstall' => true])->assertSuccessful();

    expect($this->project->read('.git/hooks/pre-push'))->toBeNull();
});

it('never touches a hook that is not a shell script', function () {
    $this->project->write('.git/hooks/pre-push', "#!/usr/bin/env node\nconsole.log('hi')\n");

    $this->artisan('larecipe:hooks:git', ['--install' => true])
        ->expectsOutputToContain('is not a shell script')
        ->assertSuccessful();

    expect($this->project->read('.git/hooks/pre-push'))->toBe("#!/usr/bin/env node\nconsole.log('hi')\n");
});

it('writes nothing on a dry run', function () {
    $this->artisan('larecipe:hooks:git', ['--install' => true, '--dry-run' => true])
        ->expectsOutputToContain('created')
        ->assertSuccessful();

    expect($this->project->read('.git/hooks/pre-push'))->toBeNull();
});
