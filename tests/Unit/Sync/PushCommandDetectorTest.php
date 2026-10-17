<?php

use LaRecipe\Domains\Sync\Support\PushCommandDetector;

it('matches commands that push code or open a pull request', function (string $command) {
    expect((new PushCommandDetector)->matches($command))->toBeTrue();
})->with([
    'git push',
    'git push origin main',
    'git push --force-with-lease -u origin feature',
    'git -C app push',
    '/usr/bin/git push',
    'cd app && git push',
    'npm test; git push -u origin feature',
    'git add . && git commit -m "wip" && git push',
    'composer test || git push',
    'FOO=1 git push',
    'env GIT_TRACE=1 git push',
    'bash -c "git push origin main"',
    "sh -c 'cd app && git push'",
    '(git push)',
    'gh pr create --fill',
    'gh pr create --title "Add tax" --body "..."',
    'glab mr create',
    'hub pull-request',
    "git commit -m \"feat: add tax\"\ngit push",
]);

it('ignores commands that only mention a push', function (string $command) {
    expect((new PushCommandDetector)->matches($command))->toBeFalse();
})->with([
    'git status',
    'git log --grep push',
    'git commit -m "git push later"',
    'echo "git push"',
    "grep -r 'git push' docs",
    'gh pr view 12',
    'gh pr list',
    'pushd app',
    'git pushx',
    'php artisan larecipe:affected',
    '',
]);

it('uses the configured patterns', function () {
    $detector = new PushCommandDetector(['git push', 'vendor/bin/deploy']);

    expect($detector->matched('cd app && vendor/bin/deploy --prod'))->toBeNull()
        ->and((new PushCommandDetector(['deploy production']))->matched('deploy production --now'))->toBe('deploy production')
        ->and((new PushCommandDetector(['deploy production']))->matches('git push'))->toBeFalse();
});
