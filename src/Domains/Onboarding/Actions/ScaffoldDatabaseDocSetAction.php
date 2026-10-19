<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Onboarding\Services\IntroductionPage;
use LaRecipe\Domains\Onboarding\Services\ProjectFacts;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationPlan;
use LaRecipe\Domains\Sources\Services\DocsMigrationRenderer;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Support\Paths;

/**
 * The database twin of ScaffoldDocSetAction: a docs migration that gives the
 * set its name and the same fact-based Introduction page, so the docs work
 * right after `php artisan migrate`. Content for a database set travels as a
 * docs migration, and so does its first page.
 *
 * Re-running the installer never writes a second one.
 */
final class ScaffoldDatabaseDocSetAction
{
    public const SLUG = 'docs_introduction';

    public function __construct(
        private readonly ProjectFacts $facts,
        private readonly IntroductionPage $introduction,
        private readonly DocsMigrationRenderer $renderer,
    ) {}

    /**
     * @param  string  $migrations  the docs migrations folder, relative to the workspace
     */
    public function execute(Workspace $workspace, string $set, string $migrations, string $appName, ?string $laravelVersion, string $timestamp): FileChange
    {
        $migrations = trim($migrations, '/');

        foreach ($workspace->files($migrations) as $existing) {
            if (str_ends_with($existing, '_'.self::SLUG.'.php')) {
                return $workspace->skip($migrations.'/'.$existing, 'Already there; LaRecipe leaves it as it is.');
            }
        }

        $facts = $this->facts->read($workspace, $appName, $laravelVersion);
        $name = $timestamp.'_'.self::SLUG;

        $plan = new DocsMigrationPlan($name, $set, new Provenance(Actor::Migration, null, null), 'Start the docs with an Introduction', [
            DocsMigrationOperation::updateManifest(null, array_filter(['name' => $facts->name, 'description' => $facts->description])),
            DocsMigrationOperation::createPage('index', $this->introduction->frontmatter($facts), $this->introduction->body($facts)),
        ]);

        $stub = $workspace->read('stubs/docs-migration.stub') ?? (string) file_get_contents(Paths::stubs('docs-migration.stub'));

        return $workspace->write($migrations.'/'.$name.'.php', $this->renderer->render($stub, $plan));
    }
}
