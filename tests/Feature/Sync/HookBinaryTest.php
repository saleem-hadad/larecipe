<?php

use LaRecipe\Tests\Feature\Sync\TemporaryProject;
use Symfony\Component\Process\Process;

/*
 * vendor/bin/larecipe-hook runs on every Bash call in an agent session. These
 * tests run the real script against a project whose `artisan` is a fake that
 * records being called, so they prove when the framework is (not) booted.
 */

beforeEach(function () {
    $this->project = TemporaryProject::create();
    $this->project->write('artisan', <<<'PHP'
        <?php
        file_put_contents(__DIR__.'/artisan-called', implode(' ', array_slice($argv, 1))."\n".stream_get_contents(STDIN));
        echo '{"hookSpecificOutput":{"hookEventName":"PreToolUse","permissionDecision":"deny","permissionDecisionReason":"held by the fake app"}}';
        PHP);
});

afterEach(fn () => $this->project->destroy());

function runHook(TemporaryProject $project, string $command, array $env = [], string $event = 'pre-push'): array
{
    $payload = json_encode([
        'session_id' => 'abc',
        'hook_event_name' => 'PreToolUse',
        'cwd' => $project->path(),
        'tool_name' => 'Bash',
        'tool_input' => ['command' => $command],
    ]);

    $process = new Process([PHP_BINARY, dirname(__DIR__, 3).'/bin/larecipe-hook', $event], $project->path(), ['CLAUDE_PROJECT_DIR' => $project->path(), ...$env]);
    $process->setInput($payload);

    $started = microtime(true);
    $process->run();

    return ['exit' => $process->getExitCode(), 'output' => $process->getOutput(), 'ms' => (microtime(true) - $started) * 1000];
}

it('exits fast and silently for commands that do not push', function () {
    $result = runHook($this->project, 'php artisan test --filter=Billing');

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toBe('')
        ->and($result['ms'])->toBeLessThan(500)
        ->and($this->project->read('artisan-called'))->toBeNull();
});

it('boots the app for a push, even inside a chained command, and relays its decision', function () {
    $result = runHook($this->project, 'composer test && git push -u origin feature');

    expect($result['exit'])->toBe(0)
        ->and($result['output'])->toContain('"permissionDecision":"deny"')
        ->and($this->project->read('artisan-called'))->toStartWith('larecipe:hook pre-push --client=claude')
        ->and($this->project->read('artisan-called'))->toContain('"command":"composer test && git push -u origin feature"');
});

it('boots the app when an agent opens a pull request', function () {
    runHook($this->project, 'gh pr create --fill');

    expect($this->project->read('artisan-called'))->not->toBeNull();
});

it('does nothing when LARECIPE_HOOKS is off', function () {
    $result = runHook($this->project, 'git push', ['LARECIPE_HOOKS' => 'off']);

    expect($result['output'])->toBe('')
        ->and($this->project->read('artisan-called'))->toBeNull();
});

it('reads the patterns compiled by larecipe:agents', function () {
    $this->project->write('bootstrap/cache/larecipe/hooks.php', "<?php return ['enabled' => true, 'push_commands' => ['deploy production']];");

    runHook($this->project, 'git push');
    expect($this->project->read('artisan-called'))->toBeNull();

    runHook($this->project, 'deploy production --now');
    expect($this->project->read('artisan-called'))->not->toBeNull();
});

it('lets the command through when the app fails', function () {
    $this->project->write('artisan', "<?php fwrite(STDERR, 'Command \"larecipe:hook\" is not defined.'); exit(1);");

    $result = runHook($this->project, 'git push');

    expect($result['exit'])->toBe(0)->and($result['output'])->toBe('');
});

it('passes session start through to the app', function () {
    runHook($this->project, '', event: 'session-start');

    expect($this->project->read('artisan-called'))->toStartWith('larecipe:hook session-start');
});
