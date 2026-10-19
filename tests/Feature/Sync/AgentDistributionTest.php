<?php

use Illuminate\Support\Facades\Blade;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

/*
 * How LaRecipe reaches agents beyond its own installer: the Laravel Boost
 * guideline, the Claude Code plugin marketplace and the no-AI CI check.
 */

function packagePath(string $path): string
{
    return dirname(__DIR__, 3).'/'.$path;
}

it('renders the Boost guideline from the same instructions as AGENTS.md', function () {
    $project = TemporaryProject::create('app')->init();
    $project->mount($this->app, config: ['larecipe.review' => 'auto']);

    $rendered = Blade::render((string) file_get_contents(packagePath('resources/boost/guidelines/core.blade.php')));

    expect($rendered)->toContain('## Documentation (LaRecipe)')
        ->and($rendered)->toContain('php artisan larecipe:affected --uncommitted --base=main')
        ->and($rendered)->toContain('Review mode is `auto`: update the docs and push without asking.')
        ->and($rendered)->toContain("@scoped(['docs/**'])")
        ->and($rendered)->toContain("@scoped(['database/migrations/docs/**'])")
        ->and($rendered)->toContain('Never write configuration or environment values into docs.');

    $project->destroy();
});

it('ships the repository as a Claude Code plugin marketplace', function () {
    $marketplace = json_decode((string) file_get_contents(packagePath('.claude-plugin/marketplace.json')), true);
    $plugin = json_decode((string) file_get_contents(packagePath('.claude-plugin/plugin.json')), true);
    $hooks = json_decode((string) file_get_contents(packagePath('claude-plugin/hooks.json')), true);

    expect($marketplace['plugins'][0]['name'])->toBe($plugin['name'])
        ->and($marketplace['plugins'][0]['source'])->toBe('./')
        ->and(packagePath(ltrim($plugin['skills'], './')).'/larecipe-write-docs/SKILL.md')->toBeFile()
        ->and(packagePath(ltrim($plugin['hooks'], './')))->toBeFile()
        ->and(packagePath(ltrim($plugin['commands'], './')).'/onboard.md')->toBeFile()
        ->and($plugin['mcpServers']['larecipe']['args'])->toBe(['${CLAUDE_PROJECT_DIR}/artisan', 'larecipe:mcp'])
        ->and($hooks['hooks']['PreToolUse'][0]['matcher'])->toBe('Bash')
        ->and($hooks['hooks']['PreToolUse'][0]['hooks'][0]['command'])->toBe('php "${CLAUDE_PLUGIN_ROOT}/bin/larecipe-hook" pre-push')
        ->and($hooks['hooks']['SessionStart'][0]['hooks'][0]['command'])->toBe('php "${CLAUDE_PLUGIN_ROOT}/bin/larecipe-hook" session-start')
        ->and($hooks['hooks'])->not->toHaveKey('Stop');
});

it('keeps the CI check read-only: no AI, no writes, no secrets', function (string $mode) {
    $project = TemporaryProject::create('app')->init();
    $project->mount($this->app);

    $this->artisan('larecipe:install', ['--agents' => 'none', '--ci' => $mode, '--no-interaction' => true])->assertSuccessful();
    $workflow = (string) $project->read('.github/workflows/larecipe-docs-check.yml');

    expect($workflow)->toContain("permissions:\n  contents: read")
        ->and($workflow)->not->toContain('contents: write')
        ->and($workflow)->not->toContain('secrets.')
        ->and($workflow)->not->toMatch('/git (push|commit)/')
        ->and($workflow)->not->toMatch('/claude|codex|anthropic|openai/i')
        ->and($workflow)->toContain('php artisan larecipe:validate --json')
        ->and($workflow)->toContain('php artisan larecipe:affected --base=origin/${{ github.base_ref }}');

    $project->destroy();
})->with(['fail', 'comment']);

it('uses no em or en dashes in anything this track ships', function () {
    $roots = ['src/Domains/Sync', 'src/Domains/Onboarding', 'resources/boost', 'stubs', 'bin', '.claude-plugin', 'claude-plugin', 'tests/Feature/Sync', 'tests/Feature/Onboarding', 'tests/Unit/Sync', 'tests/Fixtures/track-d'];

    foreach ($roots as $root) {
        $files = is_dir(packagePath($root))
            ? iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator(packagePath($root), FilesystemIterator::SKIP_DOTS)))
            : [];

        foreach ($files as $file) {
            expect(preg_match('/\x{2014}|\x{2013}/u', (string) file_get_contents($file->getPathname())))->toBe(0, $file->getPathname().' contains an em or en dash');
        }
    }
});
