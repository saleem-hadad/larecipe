<?php

namespace LaRecipe\Domains\Validation\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * php artisan larecipe:validate: every rule over every set (or one set or
 * version). Exits non-zero when there is an error, so CI and the pre-push hook
 * can stop on it; --json prints the report agents read (validate.schema.json).
 *
 * The version option is --docs-version because Symfony Console reserves
 * --version for the application itself.
 */
#[AsCommand(name: 'larecipe:validate', description: 'Check the docs: links, navigation, schemas, components, snippets, budgets and skills')]
final class ValidateCommand extends Command
{
    protected $signature = 'larecipe:validate
        {--set= : Only this doc set}
        {--docs-version= : Only this version of the set (Symfony reserves --version)}
        {--json : Print the report as JSON}';

    protected $description = 'Check the docs: links, navigation, schemas, components, snippets, budgets and skills';

    public function handle(SetRegistry $sets, ValidateDocsAction $validate): int
    {
        $only = $this->option('set');
        $version = $this->option('docs-version');

        if (is_string($only) && ! $sets->has($only)) {
            return $this->refuse("There is no doc set named \"{$only}\". Sets: ".implode(', ', array_keys($sets->configs())).'.');
        }

        $report = new ValidationReport;

        foreach (is_string($only) ? [$only] : array_keys($sets->configs()) as $key) {
            if (is_string($version) && $sets->get($key)->version($version) === null) {
                if (is_string($only)) {
                    return $this->refuse("The \"{$key}\" set has no version \"{$version}\".");
                }

                continue;
            }

            $report = $report->merge($validate->execute($key, is_string($version) ? $version : null));
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } else {
            $this->print($report);
        }

        return $report->hasErrors() ? self::FAILURE : self::SUCCESS;
    }

    private function print(ValidationReport $report): void
    {
        foreach ($report->issues as $issue) {
            $where = implode(' ', array_filter([
                $issue->set !== null && count($this->setsIn($report)) > 1 ? "[{$issue->set}]" : null,
                $issue->page ?? $issue->file,
                $issue->line !== null ? "line {$issue->line}" : null,
            ]));

            $label = $issue->level === ValidationIssue::ERROR ? '<fg=red;options=bold>ERROR</>' : '<fg=yellow;options=bold>WARN </>';
            $this->line("  {$label}  <fg=gray>{$issue->rule}</>  {$where}");
            $this->line("         {$issue->message}");

            if ($issue->fix !== null) {
                $this->line("         <fg=green>Fix:</> {$issue->fix}");
            }

            $this->newLine();
        }

        $errors = count($report->errors());
        $warnings = count($report->warnings());
        $pages = $report->pages === 1 ? '1 page' : "{$report->pages} pages";

        if ($errors === 0 && $warnings === 0) {
            $this->components->info("Checked {$pages}. No problems found.");

            return;
        }

        $summary = "Checked {$pages}: {$errors} ".($errors === 1 ? 'error' : 'errors').", {$warnings} ".($warnings === 1 ? 'warning' : 'warnings').'.';
        $errors > 0 ? $this->components->error($summary) : $this->components->warn($summary);
    }

    /** @return list<string> */
    private function setsIn(ValidationReport $report): array
    {
        return array_values(array_unique(array_filter(array_map(fn (ValidationIssue $issue) => $issue->set, $report->issues))));
    }

    private function refuse(string $message): int
    {
        $this->components->error($message);

        return self::INVALID;
    }
}
