<?php

namespace LaRecipe\Domains\Sync\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sync\Actions\InstallGitHookAction;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\Enums\FileChangeStatus;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan larecipe:hooks:git --install`: the optional git pre-push hook
 * for people who push without an agent. Warn-only by default; --strict holds
 * the push. It respects core.hooksPath and never replaces an existing hook.
 */
#[AsCommand(name: 'larecipe:hooks:git')]
final class GitHooksCommand extends Command
{
    protected $signature = 'larecipe:hooks:git
        {--install : Add the LaRecipe check to the pre-push hook}
        {--uninstall : Remove the LaRecipe check from the pre-push hook}
        {--strict : Block pushes that leave docs pages stale (default: warn only)}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Install or remove the git pre-push docs check';

    public function handle(Workspace $workspace, InstallGitHookAction $hook): int
    {
        if ($this->option('install') === $this->option('uninstall')) {
            $this->components->error('Pass --install or --uninstall.');

            return self::INVALID;
        }

        $workspace = $workspace->dryRun((bool) $this->option('dry-run'));
        $change = $hook->execute($workspace, (bool) $this->option('strict'), (bool) $this->option('uninstall'));

        if ($change->status === FileChangeStatus::Skipped) {
            $this->components->warn($change->note ?? 'Nothing to do.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail($change->path, $change->status->value);

        if ($this->option('install') && ! $workspace->isDryRun()) {
            $this->components->info($this->option('strict')
                ? 'git push now stops when it would leave docs pages stale. Skip it once with git push --no-verify.'
                : 'git push now lists docs pages it leaves stale, then pushes. Use --strict to block instead.');
        }

        return self::SUCCESS;
    }
}
