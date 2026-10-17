<?php

namespace LaRecipe\Domains\Onboarding\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Onboarding\Actions\BuildOnboardingCommandAction;
use LaRecipe\Domains\Onboarding\Actions\DetectAgentsAction;
use LaRecipe\Domains\Onboarding\Actions\InstallMcpConfigAction;
use LaRecipe\Domains\Onboarding\Actions\RunOnboardingAction;
use LaRecipe\Domains\Onboarding\Actions\ValidateWrittenPagesAction;
use LaRecipe\Domains\Onboarding\DTOs\AgentInvocation;
use LaRecipe\Domains\Onboarding\DTOs\DetectedAgent;
use LaRecipe\Domains\Onboarding\DTOs\OnboardingRequest;
use LaRecipe\Domains\Onboarding\Enums\Agent;
use LaRecipe\Domains\Onboarding\Enums\Audience;
use LaRecipe\Domains\Sync\Actions\DetectBoostAction;
use LaRecipe\Domains\Sync\Actions\InstallSkillsAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Sync\DTOs\SyncSettings;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

/**
 * `php artisan larecipe:onboard`: real starter docs from the app, written by
 * the developer's own coding agent with the larecipe-write-docs skill.
 *
 * It picks the agent, makes sure the skills are installed, shows the exact
 * command, asks before running it, streams progress, then validates and lists
 * the pages it created. Nothing is committed: the pages wait for review.
 * Without an agent, it prints what to run later.
 */
#[AsCommand(name: 'larecipe:onboard')]
final class OnboardCommand extends Command
{
    protected $signature = 'larecipe:onboard
        {--audience= : Who the docs are for: product, api, package, internal (comma-separated)}
        {--set= : The doc set to write into (default: the default set)}
        {--agent= : claude, codex, cursor, gemini or copilot (default: the one detected)}
        {--print : Only print the command, do not run it}
        {--yes : Run without asking}';

    protected $description = 'Generate starter docs from your app with your local coding agent';

    public function handle(
        Workspace $workspace,
        DocsCatalog $catalog,
        DetectAgentsAction $detect,
        DetectBoostAction $boost,
        InstallSkillsAction $skills,
        InstallMcpConfigAction $mcp,
        BuildOnboardingCommandAction $build,
        RunOnboardingAction $run,
        ValidateWrittenPagesAction $validate,
        SyncSettings $settings,
    ): int {
        $set = $catalog->set(is_string($this->option('set')) ? $this->option('set') : $settings->defaultSet);

        if ($set === null) {
            $this->components->error('There is no doc set named ['.$this->option('set').'].');

            return self::INVALID;
        }

        $audiences = $this->audiences();

        if ($audiences === []) {
            $this->components->error('Choose at least one audience with --audience=product,api,package,internal.');

            return self::INVALID;
        }

        $detected = $detect->execute($workspace);
        $agent = $this->agent($detected);
        $audienceList = implode(',', array_map(fn (Audience $a) => $a->value, $audiences));

        if ($agent === null) {
            $this->components->warn('No local coding agent found (claude, codex, cursor-agent, gemini or copilot on PATH).');
            $this->line('  Your scaffold stays as it is. Install an agent, then run:');
            $this->line("  <fg=gray>php artisan larecipe:onboard --audience={$audienceList} --set={$set->key}</>");

            return self::SUCCESS;
        }

        if (! $boost->execute($workspace)) {
            $skills->execute($workspace, ['.claude/skills', '.agents/skills']);
        }

        if ($set->isDatabase()) {
            $mcp->execute($workspace, [$agent->agent]);
        }

        $invocation = $build->execute(new OnboardingRequest($audiences, $set->key, $agent->agent, $agent->binary, $set->isDatabase() ? null : $set->docsPath));

        $this->newLine();
        $this->line($invocation->runnable ? '  LaRecipe will run this command:' : '  Send this prompt to '.$agent->agent->label().':');
        $this->newLine();
        $this->line('  '.$invocation->display);
        $this->newLine();

        if ($invocation->note !== null) {
            $this->line("  <fg=gray>{$invocation->note}</>");
            $this->newLine();
        }

        if ($this->option('print') || ! $invocation->runnable) {
            return self::SUCCESS;
        }

        if (! $agent->installed()) {
            $this->components->warn($agent->agent->label().' is configured in this project but its CLI is not on PATH. Run the command above where it is installed.');

            return self::SUCCESS;
        }

        if (! $this->consent()) {
            $this->line("  Run it later with: php artisan larecipe:onboard --audience={$audienceList} --set={$set->key} --agent={$agent->agent->value}");

            return self::SUCCESS;
        }

        return $this->onboard($invocation, $set, $workspace, $run, $validate);
    }

    private function onboard(AgentInvocation $invocation, CatalogSet $set, Workspace $workspace, RunOnboardingAction $run, ValidateWrittenPagesAction $validate): int
    {
        $watched = $set->isDatabase() ? $set->migrationsPath : (string) $set->docsPath;
        $before = $this->snapshot($workspace, $watched);

        $code = $run->execute($invocation, $workspace->root(), fn (string $line) => $this->line('  '.$line));

        $this->newLine();

        if ($code !== 0) {
            $this->components->error($invocation->agent->label()." stopped with exit code {$code}. Nothing was committed; check the docs folder before running it again.");

            return self::FAILURE;
        }

        $changed = $this->changedFiles($before, $this->snapshot($workspace, $watched), $watched);

        if ($changed === []) {
            $this->components->warn('The agent finished without changing any docs.');

            return self::SUCCESS;
        }

        $this->components->info('Pages written for your review (not committed):');

        foreach ($changed as $file => $status) {
            $this->components->twoColumnDetail($file, $status);
        }

        return $this->validate($set, $validate, array_keys($changed));
    }

    /**
     * Validate with larecipe:validate when it is available, otherwise check
     * each new page on its own with the Validation domain's page rules.
     *
     * @param  list<string>  $files
     */
    private function validate(CatalogSet $set, ValidateWrittenPagesAction $validate, array $files): int
    {
        if ($this->getApplication()?->has('larecipe:validate')) {
            return $this->call('larecipe:validate', ['--set' => $set->key]) === 0 ? self::SUCCESS : self::FAILURE;
        }

        $report = $validate->execute($set, $files);

        foreach ($report->issues as $issue) {
            $this->line("  {$issue->level}: {$issue->page}: {$issue->message}");
        }

        $errors = count($report->errors());

        $errors === 0
            ? $this->components->info('The new pages pass validation. Review them, then commit them with your code.')
            : $this->components->error("{$errors} validation errors. Fix them before committing.");

        return $errors === 0 ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<Audience> */
    private function audiences(): array
    {
        $option = $this->option('audience');

        if (is_string($option) && $option !== '') {
            return Audience::list($option);
        }

        if (! $this->input->isInteractive()) {
            return [];
        }

        $chosen = multiselect(
            label: 'Who are these docs for?',
            options: array_combine(
                array_map(fn (Audience $a) => $a->value, Audience::cases()),
                array_map(fn (Audience $a) => $a->label(), Audience::cases()),
            ),
            required: true,
        );

        return Audience::list(implode(',', $chosen));
    }

    /** @param list<DetectedAgent> $detected */
    private function agent(array $detected): ?DetectedAgent
    {
        $option = $this->option('agent');

        if (is_string($option) && $option !== '') {
            $agent = Agent::fromInput($option);

            foreach ($detected as $candidate) {
                if ($candidate->agent === $agent) {
                    return $candidate;
                }
            }

            return $agent === null ? null : new DetectedAgent($agent);
        }

        $installed = array_values(array_filter($detected, fn (DetectedAgent $d) => $d->installed()));

        if (count($installed) <= 1 || ! $this->input->isInteractive()) {
            return $installed[0] ?? null;
        }

        $choice = select(
            label: 'Which agent should write the docs?',
            options: array_combine(
                array_map(fn (DetectedAgent $d) => $d->agent->value, $installed),
                array_map(fn (DetectedAgent $d) => $d->agent->label(), $installed),
            ),
        );

        foreach ($installed as $candidate) {
            if ($candidate->agent->value === $choice) {
                return $candidate;
            }
        }

        return $installed[0];
    }

    private function consent(): bool
    {
        if ($this->option('yes')) {
            return true;
        }

        if (! $this->input->isInteractive()) {
            $this->components->warn('Not running it without your consent. Run again with --yes to run it.');

            return false;
        }

        return confirm('Run it now?', default: true);
    }

    /** @return array<string, string> file => content hash */
    private function snapshot(Workspace $workspace, string $directory): array
    {
        $snapshot = [];

        foreach ($workspace->files($directory) as $file) {
            $snapshot[$file] = md5((string) $workspace->read($directory.'/'.$file));
        }

        return $snapshot;
    }

    /**
     * @param  array<string, string>  $before
     * @param  array<string, string>  $after
     * @return array<string, string> project-relative file => created or updated
     */
    private function changedFiles(array $before, array $after, string $directory): array
    {
        $changed = [];

        foreach ($after as $file => $hash) {
            if (! isset($before[$file])) {
                $changed[trim($directory, '/').'/'.$file] = 'created';
            } elseif ($before[$file] !== $hash) {
                $changed[trim($directory, '/').'/'.$file] = 'updated';
            }
        }

        ksort($changed);

        return $changed;
    }
}
