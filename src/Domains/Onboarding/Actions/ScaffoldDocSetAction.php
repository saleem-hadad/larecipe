<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Onboarding\Services\IntroductionPage;
use LaRecipe\Domains\Onboarding\Services\JsonSettings;
use LaRecipe\Domains\Onboarding\Services\ProjectFacts;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;

/**
 * Creates a repository doc set that works on the first request: a
 * `larecipe.json` with the app's name and the schema pointer, and a short,
 * real Introduction page built from facts (the app's name, its Laravel
 * version, its first-party packages, the README's opening).
 *
 * Existing files are never touched: re-running the installer leaves a team's
 * docs exactly as they are.
 */
final class ScaffoldDocSetAction
{
    public const SCHEMA = 'https://larecipe.saleem.dev/schemas/3.x/larecipe.json';

    public function __construct(
        private readonly ProjectFacts $facts,
        private readonly IntroductionPage $introduction,
        private readonly JsonSettings $json,
    ) {}

    /** @return list<FileChange> */
    public function execute(Workspace $workspace, string $docsPath, string $appName, ?string $laravelVersion): array
    {
        $docsPath = trim($docsPath, '/');
        $facts = $this->facts->read($workspace, $appName, $laravelVersion);
        $changes = [];

        $changes[] = $this->writeNew($workspace, $docsPath.'/larecipe.json', $this->json->encode(array_filter([
            '$schema' => self::SCHEMA,
            'name' => $facts->name,
            'description' => $facts->description,
        ])));

        $landing = array_values(array_filter(
            [$docsPath.'/README.md', $docsPath.'/introduction.md'],
            fn (string $file) => $workspace->exists($file),
        ));

        $changes[] = $landing !== []
            ? $workspace->skip($landing[0], 'Already there; LaRecipe leaves it as it is.')
            : $this->writeNew($workspace, $docsPath.'/index.md', $this->introduction->render($facts));

        return $changes;
    }

    /** Write a file only when it is missing or already holds exactly this content. */
    private function writeNew(Workspace $workspace, string $file, string $contents): FileChange
    {
        $current = $workspace->read($file);

        return $current === null || $current === $contents
            ? $workspace->write($file, $contents)
            : $workspace->skip($file, 'Already there; LaRecipe leaves it as it is.');
    }
}
