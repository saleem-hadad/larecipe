<?php

namespace LaRecipe\Domains\Onboarding\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Onboarding\Actions\AddComposerScriptAction;
use LaRecipe\Domains\Onboarding\Actions\DetectAgentsAction;
use LaRecipe\Domains\Onboarding\Actions\InstallAgentHooksAction;
use LaRecipe\Domains\Onboarding\Actions\InstallMcpConfigAction;
use LaRecipe\Domains\Onboarding\Actions\ScaffoldDatabaseDocSetAction;
use LaRecipe\Domains\Onboarding\Actions\ScaffoldDocSetAction;
use LaRecipe\Domains\Onboarding\Actions\WriteAppConfigAction;
use LaRecipe\Domains\Onboarding\DTOs\DetectedAgent;
use LaRecipe\Domains\Onboarding\DTOs\InstallOptions;
use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Onboarding\Enums\Audience;
use LaRecipe\Domains\Onboarding\Services\ProjectFacts;
use LaRecipe\Domains\Sync\Actions\CompileHookPatternsAction;
use LaRecipe\Domains\Sync\Actions\DetectBoostAction;
use LaRecipe\Domains\Sync\Actions\InstallGitHookAction;
use LaRecipe\Domains\Sync\Actions\InstallSkillsAction;
use LaRecipe\Domains\Sync\Actions\LinkClaudeMdAction;
use LaRecipe\Domains\Sync\Actions\WriteCiCheckAction;
use LaRecipe\Domains\Sync\Actions\WriteInstructionsBlockAction;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\FileChange;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use LaRecipe\Domains\Sync\Enums\CiMode;
use LaRecipe\Domains\Sync\Enums\FileChangeStatus;
use LaRecipe\Domains\Sync\Enums\ReviewMode;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/**
 * `php artisan larecipe:install`: a working docs site plus everything that
 * keeps it current, in one run.
 *
 * It sets up the doc set, scaffolds a real Introduction page, writes the
 * AGENTS.md instructions, installs the skills, the MCP config and the hooks
 * for the agents this team uses, optionally adds the git hook and the no-AI
 * CI check, records the review mode, and offers onboarding last. Every step
 * is idempotent, merges into files the team owns instead of replacing them,
 * and can be previewed with --dry-run. Every prompt has a flag, so CI and
 * scripts can run it with --no-interaction.
 */
#[AsCommand(name: 'larecipe:install')]
final class InstallCommand extends Command
{
    protected $signature = 'larecipe:install
        {--driver= : repository (recommended) or database}
        {--path=docs : The docs folder of a repository set}
        {--agents= : Agents to set up, comma-separated (claude,codex,cursor,gemini,copilot), or "none" (default: the ones detected)}
        {--no-hooks : Skip the pre-push and SessionStart agent hooks}
        {--git-hook= : Also install the git pre-push hook: warn or strict}
        {--ci= : Add the optional no-AI CI check: fail or comment}
        {--review= : required (default) or auto}
        {--claude-md : Add @AGENTS.md to CLAUDE.md without asking}
        {--no-index : Leave the docs index out of AGENTS.md}
        {--composer-script : Add larecipe:agents to composer post-update-cmd without asking}
        {--onboard= : Then write starter docs for these audiences: product,api,package,internal}
        {--onboard-agent= : The agent to run onboarding with}
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Install LaRecipe: your docs, and the agent setup that keeps them current';

    public function handle(
        Workspace $workspace,
        DetectAgentsAction $detect,
        DetectBoostAction $boost,
        WriteAppConfigAction $config,
        ScaffoldDocSetAction $scaffold,
        ScaffoldDatabaseDocSetAction $scaffoldDatabase,
        WriteInstructionsBlockAction $instructions,
        LinkClaudeMdAction $claudeMd,
        InstallSkillsAction $skills,
        InstallMcpConfigAction $mcp,
        InstallAgentHooksAction $agentHooks,
        CompileHookPatternsAction $hookPatterns,
        InstallGitHookAction $gitHook,
        WriteCiCheckAction $ci,
        AddComposerScriptAction $composer,
        ProjectFacts $facts,
        SyncSettings $settings,
    ): int {
        $workspace = $workspace->dryRun((bool) $this->option('dry-run'));
        $detected = $detect->execute($workspace);
        $hasBoost = $boost->execute($workspace);

        $options = $this->gather($detected);

        if ($options === null) {
            return self::INVALID;
        }

        // 1. The doc set and its driver.
        $config->execute($workspace, $options);
        $this->useOptionsForThisRun($options, $settings->defaultSet);

        $appName = (string) $this->laravel['config']->get('app.name', 'Laravel');

        if ($options->isDatabase()) {
            $migrations = (string) $this->laravel['config']->get('larecipe.migrations.path', $this->laravel->databasePath('migrations/docs'));
            $relative = str_starts_with($migrations, $workspace->root()) ? substr($migrations, strlen(rtrim($workspace->root(), '/')) + 1) : 'database/migrations/docs';

            $scaffoldDatabase->execute($workspace, $settings->defaultSet, $relative, $appName, $this->laravel->version(), now()->format('Y_m_d_His'));
            $this->prepareDatabase($workspace->isDryRun());
        } else {
            $scaffold->execute($workspace, $options->path, $appName, $this->laravel->version());
        }

        // 2. Instructions, skills and MCP for the agents this team uses.
        if ($hasBoost) {
            $this->components->info('Laravel Boost is installed: LaRecipe\'s guideline and skills ship through Boost. Run php artisan boost:install (or boost:update) and select larecipe/larecipe.');
        } else {
            $instructions->execute($workspace, $options->index);
            $skills->execute($workspace, ['.claude/skills', '.agents/skills']);

            if ($this->wantsClaudeLink($workspace, $claudeMd, $options)) {
                $claudeMd->execute($workspace);
            }
        }

        $mcp->execute($workspace, $options->agents);

        // 3. Hooks.
        $hookAgents = array_values(array_filter($options->agents, fn (Agent $agent) => $agent->supportsHooks()));

        if ($options->hooks && $hookAgents !== []) {
            $agentHooks->execute($workspace, $hookAgents);
        }

        $hookPatterns->execute($workspace);

        if ($options->gitHook !== null) {
            $gitHook->execute($workspace, $options->gitHook === 'strict');
        }

        // 4. The optional CI check.
        if ($options->ci !== null) {
            $ci->execute($workspace, $options->ci, $facts->read($workspace, '', null)->phpVersion());
        }

        // 5. Keep it current after composer update.
        if ($options->composerScript) {
            $composer->execute($workspace);
        }

        $this->report($workspace->changes(), $workspace->isDryRun());
        $this->nextSteps($options, $hasBoost, $workspace, $settings->defaultSet);

        // 6. Onboarding, last.
        return $this->onboard($options, $detected, $workspace->isDryRun());
    }

    /** Ask for (or read the flags of) every choice the installer makes. */
    private function gather(array $detected): ?InstallOptions
    {
        $interactive = $this->input->isInteractive();

        $driver = $this->pick('driver', 'Where should the docs live?', [
            'repository' => 'In this repository, as Markdown in docs/ (recommended)',
            'database' => 'In the database, with an admin UI (changes in a pull request ship as docs migrations)',
        ], 'repository');

        if (! in_array($driver, ['repository', 'database'], true)) {
            $this->components->error("Unknown driver [{$driver}]. Use repository or database.");

            return null;
        }

        $agents = $this->agents($detected, $interactive);
        $hasHookAgent = array_filter($agents, fn (Agent $agent) => $agent->supportsHooks()) !== [];

        $hooks = ! $this->option('no-hooks') && (! $hasHookAgent || ! $interactive || confirm(
            label: 'Hold an agent\'s push once when it leaves docs pages stale? (pre-push hook, no AI)',
            default: true,
        ));

        $gitHook = $this->pick('git-hook', 'Also check pushes people make without an agent? (git pre-push hook)', [
            'none' => 'No',
            'warn' => 'Yes, list stale pages and let the push through',
            'strict' => 'Yes, and block the push',
        ], 'none');

        $ci = $this->pick('ci', 'Add the optional no-AI docs check for pull requests?', [
            'none' => 'No',
            'fail' => 'Yes, as a failing check (a skip-docs label lets a pull request through)',
            'comment' => 'Yes, as a comment that lists stale pages',
        ], 'none');

        $review = $this->pick('review', 'Review mode for docs changes', [
            'required' => 'required: the agent shows you the docs diff before committing (recommended)',
            'auto' => 'auto: the agent updates the docs and pushes without asking',
        ], 'required');

        $composerScript = (bool) $this->option('composer-script') || ($interactive && confirm(
            label: 'Refresh the agent instructions and skills after every composer update? (adds larecipe:agents to post-update-cmd)',
            default: true,
        ));

        return new InstallOptions(
            driver: $driver,
            path: trim((string) ($this->option('path') ?: 'docs'), '/'),
            agents: $agents,
            hooks: $hooks,
            gitHook: in_array($gitHook, ['warn', 'strict'], true) ? $gitHook : null,
            ci: CiMode::tryFrom((string) $ci),
            review: ReviewMode::fromConfig($review),
            claudeMd: (bool) $this->option('claude-md'),
            index: ! $this->option('no-index'),
            composerScript: $composerScript,
            onboard: is_string($this->option('onboard')) ? Audience::list($this->option('onboard')) : [],
            onboardAgent: is_string($this->option('onboard-agent')) ? Agent::fromInput($this->option('onboard-agent')) : null,
        );
    }

    /**
     * A flag's value, or the answer to a select prompt, or the default when
     * nobody can be asked.
     *
     * @param  array<string, string>  $options
     */
    private function pick(string $option, string $label, array $options, string $default): string
    {
        $value = $this->option($option);

        if (is_string($value) && $value !== '') {
            return strtolower($value);
        }

        return $this->input->isInteractive() ? (string) select(label: $label, options: $options, default: $default) : $default;
    }

    /**
     * @param  list<DetectedAgent>  $detected
     * @return list<Agent>
     */
    private function agents(array $detected, bool $interactive): array
    {
        $option = $this->option('agents');

        if (is_string($option) && $option !== '') {
            if (strtolower(trim($option)) === 'none') {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(Agent::fromInput(...), explode(',', $option))), SORT_REGULAR));
        }

        $found = array_map(fn (DetectedAgent $agent) => $agent->agent, $detected);

        if (! $interactive) {
            return $found;
        }

        $chosen = multiselect(
            label: 'Which coding agents should LaRecipe set up? (MCP server, hooks)',
            options: array_combine(array_map(fn (Agent $a) => $a->value, Agent::cases()), array_map(fn (Agent $a) => $a->label(), Agent::cases())),
            default: array_map(fn (Agent $a) => $a->value, $found),
            hint: $detected === [] ? 'None detected on this machine.' : 'Detected: '.implode(', ', array_map(fn (DetectedAgent $d) => $d->describe(), $detected)),
        );

        return array_values(array_filter(array_map(fn ($value) => Agent::tryFrom((string) $value), $chosen)));
    }

    /**
     * Later steps (the AGENTS.md block, the MCP config) read the set's config,
     * so this run uses the choices right away, even before config/larecipe.php
     * is published or on a dry run.
     */
    private function useOptionsForThisRun(InstallOptions $options, string $set): void
    {
        $config = $this->laravel['config'];
        $config->set('larecipe.review', $options->review->value);
        $config->set("larecipe.sets.{$set}.source.driver", $options->driver);

        if (! $options->isDatabase()) {
            $config->set("larecipe.sets.{$set}.source.path", $this->laravel->basePath($options->path));
        }

        $this->laravel->forgetScopedInstances();
    }

    /**
     * The database driver needs its tables and a persistent cache. The tables load
     * from the package, so `php artisan migrate` creates them; publishing them is
     * only for teams who want to customize them.
     */
    private function prepareDatabase(bool $dryRun): void
    {
        $this->components->twoColumnDetail('database tables', 'run php artisan migrate: it creates the tables and applies the starter docs migration');

        $config = $this->laravel['config'];
        $store = (string) ($config->get('larecipe.cache.store') ?: $config->get('cache.default'));
        $driver = (string) $config->get("cache.stores.{$store}.driver", $store);

        if (in_array($driver, ['array', 'null'], true)) {
            $this->components->warn("The cache store [{$store}] does not persist. A database set always runs behind a cache: set LARECIPE_CACHE_STORE to a persistent store (redis, database, file).");
        }
    }

    private function wantsClaudeLink(Workspace $workspace, LinkClaudeMdAction $claudeMd, InstallOptions $options): bool
    {
        if (! $claudeMd->execute($workspace, apply: false)->changed()) {
            return false;
        }

        if ($options->claudeMd) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->warn('CLAUDE.md does not import AGENTS.md, so Claude Code will not read the LaRecipe instructions. Run php artisan larecipe:agents-md --claude-md to add @AGENTS.md to it.');

            return false;
        }

        return confirm('CLAUDE.md does not import AGENTS.md, so Claude Code will not read the LaRecipe instructions. Add @AGENTS.md to CLAUDE.md?', default: true);
    }

    /** @param list<FileChange> $changes */
    private function report(array $changes, bool $dryRun): void
    {
        $this->newLine();
        $this->line($dryRun ? '  <options=bold>Dry run: nothing was written.</>' : '  <options=bold>Files</>');

        $latest = [];
        $weight = fn (FileChangeStatus $status) => match ($status) {
            FileChangeStatus::Created => 3,
            FileChangeStatus::Updated, FileChangeStatus::Deleted => 2,
            FileChangeStatus::Skipped => 1,
            FileChangeStatus::Unchanged => 0,
        };

        foreach ($changes as $change) {
            // One line per skills folder instead of one per skill file; the biggest change wins.
            if (preg_match('#^(.*skills)/larecipe-[^/]+/#', $change->path, $match)) {
                $key = $match[1].'/larecipe-*';
                $previous = $latest[$key] ?? null;
                $latest[$key] = $previous !== null && $weight($previous->status) >= $weight($change->status) ? $previous : new FileChange($key, $change->status);

                continue;
            }

            $latest[$change->path] = $change;
        }

        $unchanged = 0;

        foreach ($latest as $change) {
            if ($change->status === FileChangeStatus::Unchanged) {
                $unchanged++;

                continue;
            }

            $status = $dryRun && $change->changed() ? 'would be '.$change->status->value : $change->status->value;
            $this->components->twoColumnDetail($change->path, $change->status === FileChangeStatus::Skipped ? "skipped: {$change->note}" : $status);
        }

        if ($unchanged > 0) {
            $this->components->twoColumnDetail("{$unchanged} ".($unchanged === 1 ? 'file' : 'files').' already up to date', 'unchanged');
        }
    }

    private function nextSteps(InstallOptions $options, bool $hasBoost, Workspace $workspace, string $set): void
    {
        $steps = ['Open /'.trim((string) $this->laravel['config']->get("larecipe.sets.{$set}.route.prefix", $set), '/').' in your browser to see the docs.'];

        if ($options->has(Agent::Codex)) {
            $steps[] = 'Codex: trust this project in ~/.codex/config.toml and approve the LaRecipe hooks with /hooks, so Codex loads them.';
        }

        if (! $options->composerScript && ! $hasBoost) {
            $steps[] = 'Keep agents current after updates: add "'.AddComposerScriptAction::SCRIPT.'" to composer.json post-update-cmd.';
        }

        if (! $workspace->isDirectory('vendor/laravel/ai')) {
            $steps[] = 'Ask AI for readers and larecipe:sync need laravel/ai: composer require laravel/ai';
        }

        $steps[] = 'Commit the files above, so every teammate\'s agent gets the same setup.';

        $this->newLine();
        $this->line('  <options=bold>Next</>');

        foreach ($steps as $step) {
            $this->line('  - '.$step);
        }

        $this->newLine();
    }

    /** @param list<DetectedAgent> $detected */
    private function onboard(InstallOptions $options, array $detected, bool $dryRun): int
    {
        $audiences = $options->onboard;
        $installed = array_values(array_filter($detected, fn (DetectedAgent $agent) => $agent->installed()));

        if ($audiences === [] && $this->input->isInteractive() && $installed !== [] && confirm('Generate starter docs from your app with your coding agent now?', default: false)) {
            $audiences = Audience::list(implode(',', multiselect(
                label: 'Who are these docs for?',
                options: array_combine(array_map(fn (Audience $a) => $a->value, Audience::cases()), array_map(fn (Audience $a) => $a->label(), Audience::cases())),
                required: true,
            )));
        }

        if ($audiences === []) {
            if ($installed === []) {
                $this->line('  When a coding agent is installed, write starter docs from your app with: php artisan larecipe:onboard --audience=api');
            }

            return self::SUCCESS;
        }

        return $this->call('larecipe:onboard', array_filter([
            '--audience' => implode(',', array_map(fn (Audience $a) => $a->value, $audiences)),
            '--agent' => $options->onboardAgent?->value,
            '--print' => $dryRun,
            '--no-interaction' => ! $this->input->isInteractive(),
        ]));
    }
}
