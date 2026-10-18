<?php

use LaRecipe\Domains\Onboarding\Actions\InstallAgentHooksAction;
use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Sync\Services\LocalWorkspace;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;
use Symfony\Component\Process\Process;

/*
 * The hooks change into the app's folder inside the repository. That folder
 * name goes into a shell command, so a name such as site$(touch pwned) must
 * stay a name and never run.
 */

const EVIL_FOLDER = 'site$(touch pwned)';

beforeEach(function () {
    $this->project = TemporaryProject::create()->init();
    mkdir($this->project->path(EVIL_FOLDER));
    $this->app->setBasePath($this->project->path(EVIL_FOLDER));
    $this->app['config']->set('larecipe.sets', []);
});

afterEach(fn () => $this->project->destroy());

function runShell(string $script, string $cwd): Process
{
    $process = Process::fromShellCommandline($script, $cwd);
    $process->run();

    return $process;
}

it('quotes the app folder in the git pre-push hook', function () {
    $this->artisan('larecipe:hooks:git', ['--install' => true])->assertSuccessful();

    $hook = $this->project->read('.git/hooks/pre-push');
    runShell('sh .git/hooks/pre-push', $this->project->path());

    expect($hook)->toContain("(cd \"\$(git rev-parse --show-toplevel)\"/'site\$(touch pwned)' && php artisan larecipe:hook git-pre-push)")
        ->and(file_exists($this->project->path('pwned')))->toBeFalse()
        ->and(file_exists($this->project->path(EVIL_FOLDER.'/pwned')))->toBeFalse();
});

it('quotes the app folder in the Codex hook, which stays valid JSON', function () {
    app(InstallAgentHooksAction::class)->execute(new LocalWorkspace($this->project->path(EVIL_FOLDER)), [Agent::Codex]);

    $settings = json_decode((string) $this->project->read(EVIL_FOLDER.'/.codex/hooks.json'), true, flags: JSON_THROW_ON_ERROR);
    $command = $settings['hooks']['PreToolUse'][0]['hooks'][0]['command'];

    runShell($command.' < /dev/null', $this->project->path());

    expect($command)->toBe("\"\$(git rev-parse --show-toplevel)\"/'site\$(touch pwned)/vendor/bin/larecipe-hook' pre-push --client=codex")
        ->and(file_exists($this->project->path('pwned')))->toBeFalse();
});
