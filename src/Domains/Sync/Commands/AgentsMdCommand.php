<?php

namespace LaRecipe\Domains\Sync\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sync\Actions\BuildInstructionsAction;
use LaRecipe\Domains\Sync\Actions\DetectBoostAction;
use LaRecipe\Domains\Sync\Actions\LinkClaudeMdAction;
use LaRecipe\Domains\Sync\Actions\WriteInstructionsBlockAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;

/**
 * `php artisan larecipe:agents-md`: writes the compressed docs index (one line
 * per page) into the LaRecipe block of AGENTS.md, under the instructions, so
 * every coding session has the map of the docs. With consent, it also makes
 * CLAUDE.md import AGENTS.md.
 */
#[AsCommand(name: 'larecipe:agents-md')]
final class AgentsMdCommand extends Command
{
    protected $signature = 'larecipe:agents-md
        {--set= : Index this doc set (default: the default set)}
        {--claude-md : Also add @AGENTS.md to CLAUDE.md without asking}
        {--print : Print the block instead of writing it}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Write the docs index into the LaRecipe block of AGENTS.md';

    public function handle(
        Workspace $workspace,
        DocsCatalog $catalog,
        DetectBoostAction $boost,
        BuildInstructionsAction $build,
        WriteInstructionsBlockAction $write,
        LinkClaudeMdAction $claude,
        SyncSettings $settings,
    ): int {
        $set = $this->option('set');

        if (is_string($set) && $catalog->set($set) === null) {
            $this->components->error("There is no doc set named [{$set}].");

            return self::INVALID;
        }

        $set = is_string($set) ? $set : null;

        if ($this->option('print')) {
            $this->output->writeln($build->execute(true, $set), OutputInterface::OUTPUT_RAW);

            return self::SUCCESS;
        }

        $workspace = $workspace->dryRun((bool) $this->option('dry-run'));

        if ($boost->execute($workspace)) {
            $this->components->info('Laravel Boost is installed: the LaRecipe guideline carries this index. Run php artisan boost:update to refresh it.');

            return self::SUCCESS;
        }

        $change = $write->execute($workspace, true, $set);
        $this->components->twoColumnDetail($change->path, $change->status->value);

        if ($claude->execute($workspace, apply: false)->changed() && $this->claudeConsent()) {
            $link = $claude->execute($workspace);
            $this->components->twoColumnDetail($link->path, $link->status->value);
        }

        return self::SUCCESS;
    }

    private function claudeConsent(): bool
    {
        if ($this->option('claude-md')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->warn('CLAUDE.md does not import AGENTS.md, so Claude Code will not read the LaRecipe block. Run again with --claude-md to add @AGENTS.md to it.');

            return false;
        }

        return confirm('CLAUDE.md does not import AGENTS.md, so Claude Code will not read the LaRecipe block. Add @AGENTS.md to CLAUDE.md?', default: true);
    }
}
