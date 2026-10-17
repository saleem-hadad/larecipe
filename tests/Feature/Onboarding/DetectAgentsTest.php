<?php

use LaRecipe\Domains\Onboarding\Actions\DetectAgentsAction;
use LaRecipe\Domains\Onboarding\DTOs\DetectedAgent;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Tests\Feature\Onboarding\FakeAgents;
use LaRecipe\Tests\Feature\Sync\TemporaryProject;

it('finds agent CLIs on PATH and agent folders in the project', function () {
    $project = TemporaryProject::create('fresh');
    $project->write('.cursor/rules/team.mdc', 'rules')->write('GEMINI.md', '# Gemini');
    $project->mount($this->app);
    $agents = (new FakeAgents(['claude', 'cursor-agent']))->detectIn($this->app);

    $detected = app(DetectAgentsAction::class)->execute(app(Workspace::class));
    $summary = array_map(fn (DetectedAgent $d) => [$d->agent->value, $d->installed(), $d->markers], $detected);

    expect($summary)->toBe([
        ['claude', true, []],
        ['cursor', true, ['.cursor']],
        ['gemini', false, ['GEMINI.md']],
    ]);

    $agents->destroy();
    $project->destroy();
});
