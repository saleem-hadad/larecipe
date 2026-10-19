<?php

namespace LaRecipe\Domains\Sync\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sync\Actions\CompileHookPatternsAction;
use LaRecipe\Domains\Sync\Actions\DetectBoostAction;
use LaRecipe\Domains\Sync\Actions\InstallSkillsAction;
use LaRecipe\Domains\Sync\Actions\WriteInstructionsBlockAction;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Support\Paths;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan larecipe:agents`: brings everything LaRecipe gives coding
 * agents up to date after a package or config change. It regenerates the
 * AGENTS.md block, re-syncs the four skills with references generated from
 * this app's components and schemas, and compiles the hook patterns.
 *
 * Suggested as a Composer `post-update-cmd`, so the instructions never fall
 * behind the installed version. With Laravel Boost, the guideline and skills
 * ship through Boost, and this command only refreshes the generated
 * references in the copies Boost wrote.
 */
#[AsCommand(name: 'larecipe:agents')]
final class AgentsCommand extends Command
{
    /** Every folder an agent reads skills from, as Laravel Boost writes them. */
    public const SKILL_FOLDERS = ['.claude/skills', '.agents/skills', '.github/skills', '.cursor/skills', '.junie/skills', '.kiro/skills', '.factory/skills', '.grok/skills', '.pi/skills', '.ai/skills'];

    /** Where LaRecipe installs skills itself when Boost is not managing them. */
    public const DEFAULT_SKILL_FOLDERS = ['.claude/skills', '.agents/skills'];

    protected $signature = 'larecipe:agents
        {--dry-run : Show what would change without writing anything}
        {--no-index : Leave the docs index out of the AGENTS.md block}
        {--package : Refresh the canonical skills inside the LaRecipe package (for contributors)}';

    protected $description = 'Regenerate the AGENTS.md block, the LaRecipe skills and the hook patterns';

    public function handle(
        Workspace $workspace,
        DetectBoostAction $boost,
        WriteInstructionsBlockAction $instructions,
        InstallSkillsAction $skills,
        CompileHookPatternsAction $hooks,
    ): int {
        $workspace = $workspace->dryRun((bool) $this->option('dry-run'));

        if ($this->option('package')) {
            $skills->execute($workspace->at(Paths::resources('boost/skills')), [''], referencesOnly: false, canonical: true);

            return $this->report($workspace->changes(), $workspace->isDryRun());
        }

        if ($boost->execute($workspace)) {
            $this->components->info('Laravel Boost is installed: LaRecipe\'s guideline and skills ship through Boost. Run php artisan boost:update to refresh them.');
            $skills->execute($workspace, self::SKILL_FOLDERS, referencesOnly: true);
        } else {
            $instructions->execute($workspace, $this->option('no-index') ? false : null);
            $skills->execute($workspace, $this->skillFolders($workspace));
        }

        $hooks->execute($workspace);

        return $this->report($workspace->changes(), $workspace->isDryRun());
    }

    /** @return list<string> the folders that already hold LaRecipe's skills, or the defaults */
    private function skillFolders(Workspace $workspace): array
    {
        $installed = array_values(array_filter(
            self::DEFAULT_SKILL_FOLDERS,
            fn (string $folder) => $workspace->exists($folder.'/larecipe-write-docs/SKILL.md'),
        ));

        return $installed === [] ? self::DEFAULT_SKILL_FOLDERS : $installed;
    }

    /** @param list<FileChange> $changes */
    private function report(array $changes, bool $dryRun): int
    {
        $changed = array_values(array_filter($changes, fn (FileChange $change) => $change->changed()));

        foreach ($changed as $change) {
            $this->components->twoColumnDetail($change->path, $change->status->value);
        }

        $count = count($changed);
        $this->components->info(match (true) {
            $dryRun => "Dry run: {$count} ".($count === 1 ? 'file' : 'files').' would change.',
            $count === 0 => 'Everything LaRecipe gives your agents is up to date.',
            default => "Updated {$count} ".($count === 1 ? 'file' : 'files').'.',
        });

        return self::SUCCESS;
    }
}
