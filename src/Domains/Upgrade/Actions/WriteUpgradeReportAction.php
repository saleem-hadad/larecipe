<?php

namespace LaRecipe\Domains\Upgrade\Actions;

use LaRecipe\Domains\Upgrade\DTOs\PlannedFile;
use LaRecipe\Domains\Upgrade\DTOs\ReportEntry;
use LaRecipe\Domains\Upgrade\DTOs\UpgradePlan;
use LaRecipe\Support\Contracts\Clock;

/**
 * Writes larecipe-upgrade-report.md for people and larecipe-upgrade-report.json
 * for the larecipe-upgrade-v2 skill. Both say the same thing: what changed, what
 * was dropped on purpose, what is left for a person, and how validation went.
 */
final class WriteUpgradeReportAction
{
    public const MARKDOWN = 'larecipe-upgrade-report.md';

    public const JSON = 'larecipe-upgrade-report.json';

    public function __construct(private readonly Clock $clock) {}

    /**
     * @param  array{pages: int, errors: int, warnings: int, issues: list<array>}  $validation
     * @return array{markdown: string, json: string} the paths written, relative to the base path
     */
    public function execute(UpgradePlan $plan, array $validation, bool $applied): array
    {
        $project = $plan->project;

        file_put_contents($project->path(self::MARKDOWN), $this->markdown($plan, $validation, $applied));
        file_put_contents($project->path(self::JSON), json_encode($this->json($plan, $validation, $applied), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");

        return ['markdown' => self::MARKDOWN, 'json' => self::JSON];
    }

    private function json(UpgradePlan $plan, array $validation, bool $applied): array
    {
        return [
            'version' => 1,
            'generated_at' => $this->clock->now()->format(DATE_ATOM),
            'applied' => $applied,
            'docs' => ['from' => $plan->project->docsPath(), 'to' => $plan->project->targetDocsPath()],
            'versions' => $plan->project->versions(),
            'summary' => $this->summary($plan, $validation),
            'composer' => $plan->composer,
            'files' => array_map(fn (PlannedFile $file) => $file->toArray(), $plan->files),
            'manual' => array_map(fn (ReportEntry $entry) => $entry->toArray(), $plan->entries(ReportEntry::MANUAL)),
            'changed' => array_map(fn (ReportEntry $entry) => $entry->toArray(), $plan->entries(ReportEntry::CHANGED)),
            'dropped' => array_map(fn (ReportEntry $entry) => $entry->toArray(), $plan->entries(ReportEntry::DROPPED)),
            'validation' => $validation,
        ];
    }

    private function summary(UpgradePlan $plan, array $validation): array
    {
        return [
            'pages' => count($plan->pages),
            'files_written' => count(array_filter($plan->files, fn (PlannedFile $file) => $file->operation !== PlannedFile::DELETE)),
            'files_removed' => count(array_filter($plan->files, fn (PlannedFile $file) => $file->operation === PlannedFile::DELETE)),
            'manual' => count($plan->entries(ReportEntry::MANUAL)),
            'errors' => $validation['errors'],
            'warnings' => $validation['warnings'],
        ];
    }

    private function markdown(UpgradePlan $plan, array $validation, bool $applied): string
    {
        $summary = $this->summary($plan, $validation);
        $lines = [
            '# LaRecipe upgrade report',
            '',
            'Written by `php artisan larecipe:upgrade` on '.$this->clock->now()->format('Y-m-d').'. '.($applied
                ? 'These changes were applied. Review the diff before you commit it.'
                : 'This was a dry run: nothing was written. Run `php artisan larecipe:upgrade --apply` to make these changes.'),
            '',
            '## Summary',
            '',
            '| | |',
            '| --- | --- |',
            '| Versions | '.(implode(', ', $plan->project->versions()) ?: 'none').' |',
            '| Docs folder | `'.$plan->project->docsPath().'/`'.($plan->project->docsPath() !== $plan->project->targetDocsPath() ? ' moves to `'.$plan->project->targetDocsPath().'/`' : '').' |',
            "| Pages converted | {$summary['pages']} |",
            "| Files written | {$summary['files_written']} |",
            "| Files removed | {$summary['files_removed']} |",
            "| Left for you | {$summary['manual']} |",
            "| Validation | {$summary['errors']} errors, {$summary['warnings']} warnings |",
            '',
            '## 1. Composer and code',
            '',
        ];

        if ($plan->composer !== []) {
            array_push($lines, 'Run these, in order:', '', '```bash', ...$plan->composer);
            array_push($lines, '```', '');
        }

        array_push(
            $lines,
            '- The namespace is now `LaRecipe\\` (it was `BinaryTorch\\LaRecipe\\`).',
            '- The `viewLarecipe` gate now receives the page and the set key: `fn ($user, $page = null, $set = null)`, where `$page` has `ref->path`, `ref->version`, `title` and `private`.',
            '- Publish the new config only if you want the full reference: `php artisan vendor:publish --tag=larecipe-config` (this upgrade already wrote a working `config/larecipe.php`).',
            '',
            '## 2. Left for you',
            '',
        );

        $manual = $plan->entries(ReportEntry::MANUAL);

        if ($manual === []) {
            $lines[] = 'Nothing. Every change was mechanical.';
        } else {
            $lines[] = 'The `larecipe-upgrade-v2` skill reads `'.self::JSON.'` and works through this list with you.';
            $lines[] = '';

            foreach ($manual as $entry) {
                $lines[] = '- [ ] '.$this->where($entry).$entry->message.($entry->fix !== null ? ' '.$entry->fix : '');
            }
        }

        array_push($lines, '', '## 3. Files', '', '| Change | File | Why |', '| --- | --- | --- |');

        foreach ($plan->files as $file) {
            $lines[] = '| '.$file->operation.' | `'.$file->path.'` | '.($file->summary ?? '').' |';
        }

        array_push($lines, '', '## 4. What changed', '');

        foreach ($plan->entries(ReportEntry::CHANGED) as $entry) {
            $lines[] = '- '.$this->where($entry).$entry->message;
        }

        array_push($lines, '', '## 5. Dropped on purpose', '');

        $dropped = $plan->entries(ReportEntry::DROPPED);

        if ($dropped === []) {
            $lines[] = 'Nothing.';
        }

        foreach ($dropped as $entry) {
            $lines[] = '- '.$this->where($entry).$entry->message.($entry->fix !== null ? ' '.$entry->fix : '');
        }

        array_push($lines, '', '## 6. Validation', '');

        if ($validation['issues'] === []) {
            $lines[] = "All {$validation['pages']} converted pages render with the v3 renderer, and every internal link resolves.";
        } else {
            array_push($lines, "{$validation['pages']} pages checked: {$validation['errors']} errors, {$validation['warnings']} warnings.", '', '| Level | Rule | Page | Message |', '| --- | --- | --- | --- |');

            foreach ($validation['issues'] as $issue) {
                $lines[] = '| '.$issue['level'].' | `'.$issue['rule'].'` | `'.($issue['page'] ?? '').'` | '.str_replace('|', '\\|', $issue['message'].(isset($issue['fix']) ? ' '.$issue['fix'] : '')).' |';
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function where(ReportEntry $entry): string
    {
        if ($entry->file === null) {
            return '';
        }

        return '`'.$entry->file.($entry->line !== null ? ':'.$entry->line : '').'`: ';
    }
}
