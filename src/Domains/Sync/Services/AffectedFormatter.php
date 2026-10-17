<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\AffectedPage;
use LaRecipe\Domains\Sync\DTOs\AffectedReport;
use LaRecipe\Domains\Sync\DTOs\DriftedPage;
use LaRecipe\Domains\Sync\Enums\AffectedFormat;
use LaRecipe\Domains\Sync\Enums\AffectedReason;

/**
 * Prints an affected report for whoever reads it: a person in a terminal
 * (text), a program (json), GitHub Actions (annotations) or a pull request
 * comment (markdown).
 */
final class AffectedFormatter
{
    public const COMMENT_MARKER = '<!-- larecipe:docs-check -->';

    public function format(AffectedReport $report, AffectedFormat $format, bool $failOnStale = false): string
    {
        return match ($format) {
            AffectedFormat::Json => (string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            AffectedFormat::Github => $this->github($report, $failOnStale),
            AffectedFormat::Markdown => $this->markdown($report),
            AffectedFormat::Text => $this->text($report),
        };
    }

    private function text(AffectedReport $report): string
    {
        $lines = [];

        foreach ($report->notes as $note) {
            $lines[] = 'Note: '.$note;
        }

        $range = $this->range($report);
        $count = count($report->changedFiles);
        $lines[] = "LaRecipe: {$count} ".($count === 1 ? 'file' : 'files')." changed {$range}.";

        $stale = $report->stale();
        $updated = $report->updated();

        if ($stale !== []) {
            $lines[] = '';
            $lines[] = 'Stale (the code changed, the page did not):';
            array_push($lines, ...$this->list($stale));
        }

        if ($updated !== []) {
            $lines[] = '';
            $lines[] = 'Updated in this change:';
            array_push($lines, ...$this->list($updated));
        }

        if ($report->drift !== []) {
            $lines[] = '';
            $lines[] = 'Drifted earlier (what they document changed after they were last updated):';

            foreach ($report->drift as $page) {
                $lines[] = '  - '.$page->page->label().'   '.$this->driftWhy($page);
            }
        }

        if ($report->pages === [] && $report->drift === []) {
            $lines[] = 'No docs pages are affected.';
        } elseif ($stale !== []) {
            $lines[] = '';
            $lines[] = 'Update the stale pages with the larecipe-update-docs skill, then run php artisan larecipe:validate.';
        }

        return implode("\n", $lines);
    }

    private function github(AffectedReport $report, bool $failOnStale): string
    {
        $lines = [];

        foreach ($report->notes as $note) {
            $lines[] = '::warning title=LaRecipe::'.$this->escapeData($note);
        }

        foreach ($report->stale() as $page) {
            $level = $failOnStale ? 'error' : 'warning';
            $file = $page->page->file !== null ? 'file='.$this->escapeProperty($page->page->file).',' : '';
            $message = $page->reason === AffectedReason::Translation
                ? 'The source page changed in this pull request ('.implode(', ', $page->changedFiles).') but this translation did not.'
                : 'This pull request changes code this page documents ('.implode(', ', $page->changedFiles).') but not the page. Update it, or add the skip-docs label when no docs change is needed.';
            $lines[] = "::{$level} {$file}title=Stale docs page: ".$this->escapeProperty($page->page->label()).'::'.$this->escapeData($message);
        }

        foreach ($report->drift as $page) {
            $file = $page->page->file !== null ? 'file='.$this->escapeProperty($page->page->file).',' : '';
            $lines[] = "::notice {$file}title=Docs page drifted earlier::".$this->escapeData($this->driftWhy($page));
        }

        $stale = count($report->stale());
        $lines[] = $stale === 0
            ? 'LaRecipe: no docs pages need an update for this change.'
            : "LaRecipe: {$stale} docs ".($stale === 1 ? 'page needs' : 'pages need').' an update for this change.';

        return implode("\n", $lines);
    }

    private function markdown(AffectedReport $report): string
    {
        $stale = $report->stale();
        $lines = ['### LaRecipe docs check', ''];

        if ($stale === []) {
            $lines[] = 'No docs pages need an update for this pull request.';
        } else {
            $count = count($stale);
            $lines[] = $count === 1
                ? 'This pull request changes code that 1 docs page describes, and the page was not updated:'
                : "This pull request changes code that {$count} docs pages describe, and they were not updated:";
            $lines[] = '';
            $lines[] = '| Page | Sources | Changed files |';
            $lines[] = '| --- | --- | --- |';

            foreach ($stale as $page) {
                $lines[] = sprintf(
                    '| `%s` | %s | %s |',
                    $page->page->label(),
                    implode(', ', array_map(fn ($s) => "`{$s}`", $page->matchedSources)),
                    implode(', ', array_map(fn ($f) => "`{$f}`", $page->changedFiles)),
                );
            }

            $lines[] = '';
            $lines[] = 'Update them in this pull request (the `larecipe-update-docs` skill does it in your agent session), or add the `skip-docs` label when no docs change is needed.';
        }

        if ($report->drift !== []) {
            $lines[] = '';
            $lines[] = '<details><summary>'.count($report->drift).' pages drifted before this pull request</summary>';
            $lines[] = '';

            foreach ($report->drift as $page) {
                $lines[] = '- `'.$page->page->label().'`: '.$this->driftWhy($page);
            }

            $lines[] = '';
            $lines[] = '</details>';
        }

        $lines[] = '';
        $lines[] = self::COMMENT_MARKER;

        return implode("\n", $lines);
    }

    /** @param list<AffectedPage> $pages @return list<string> */
    private function list(array $pages): array
    {
        $labels = array_map(fn (AffectedPage $page) => $page->page->label(), $pages);
        $width = max(array_map('strlen', $labels));
        $lines = [];

        foreach ($pages as $index => $page) {
            $why = $page->reason === AffectedReason::Translation
                ? 'source page: '.implode(', ', $page->matchedSources)
                : 'sources: '.implode(', ', $page->matchedSources);
            $lines[] = '  - '.str_pad($labels[$index], $width).'   ('.$why.')';
        }

        return $lines;
    }

    private function driftWhy(DriftedPage $page): string
    {
        $what = $page->reason === AffectedReason::Translation ? 'its source page changed' : 'its sources changed';

        return "{$what} in {$page->staleSince->short()} ({$page->staleSince->date->format('Y-m-d')}): ".$page->staleSince->subject;
    }

    private function range(AffectedReport $report): string
    {
        $query = $report->query;

        if ($query->base === null) {
            return 'in uncommitted work';
        }

        $range = "since {$query->base}".($query->head !== 'HEAD' ? " up to {$query->head}" : '');

        return $query->includesUncommitted() ? $range.', plus uncommitted work' : $range;
    }

    private function escapeData(string $value): string
    {
        return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
    }

    private function escapeProperty(string $value): string
    {
        return str_replace(['%', "\r", "\n", ':', ','], ['%25', '%0D', '%0A', '%3A', '%2C'], $value);
    }
}
