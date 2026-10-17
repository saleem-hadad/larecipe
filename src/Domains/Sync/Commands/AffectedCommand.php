<?php

namespace LaRecipe\Domains\Sync\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sync\Actions\FindAffectedPagesAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\DTOs\AffectedQuery;
use LaRecipe\Domains\Sync\Enums\AffectedFormat;
use LaRecipe\Domains\Sync\Services\AffectedFormatter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php artisan larecipe:affected`: which docs pages a change affects, which
 * of them it left stale, and which drifted before it. The same check the
 * pre-push hook and the optional CI check run.
 */
#[AsCommand(name: 'larecipe:affected')]
final class AffectedCommand extends Command
{
    protected $signature = 'larecipe:affected
        {--base= : Compare with this branch or commit (from where the branch left it)}
        {--head=HEAD : The end of the range}
        {--uncommitted : Include staged, unstaged and untracked changes}
        {--set= : Only this doc set}
        {--docs-version= : A version id, or "all" (default: each set\'s default version)}
        {--locale= : Check this locale\'s translations against their source pages}
        {--format=text : text, json, github or markdown}
        {--fail-on-stale : Exit with 1 when the change leaves pages stale}
        {--no-drift : Skip pages that drifted before this change}';

    protected $description = 'List the docs pages a change affects and the ones it left stale';

    public function handle(FindAffectedPagesAction $affected, AffectedFormatter $formatter, DocsCatalog $catalog): int
    {
        $format = AffectedFormat::tryFrom((string) $this->option('format'));

        if ($format === null) {
            $this->components->error('Unknown format ['.$this->option('format').']. Use text, json, github or markdown.');

            return self::INVALID;
        }

        $set = $this->option('set');

        if (is_string($set) && $catalog->set($set) === null) {
            $this->components->error("There is no doc set named [{$set}].");

            return self::INVALID;
        }

        $report = $affected->execute(new AffectedQuery(
            set: is_string($set) ? $set : null,
            base: $this->stringOption('base'),
            head: $this->stringOption('head') ?? 'HEAD',
            uncommitted: (bool) $this->option('uncommitted'),
            version: $this->stringOption('docs-version'),
            locale: $this->stringOption('locale'),
            drift: ! $this->option('no-drift'),
        ));

        $failOnStale = (bool) $this->option('fail-on-stale');
        $text = $formatter->format($report, $format, $failOnStale);

        // JSON is one document; every other format is read line by line.
        foreach ($format === AffectedFormat::Json ? [$text] : explode("\n", $text) as $line) {
            $this->output->writeln($line, OutputInterface::OUTPUT_RAW);
        }

        if ($format === AffectedFormat::Github) {
            $this->writeGithubFiles($formatter->format($report, AffectedFormat::Markdown), count($report->stale()));
        }

        return $failOnStale && $report->hasStale() ? self::FAILURE : self::SUCCESS;
    }

    /**
     * In GitHub Actions, add the Markdown report to the job summary and expose
     * the stale count as a step output. These are the runner's files, not the
     * repository's: the check never writes to the repository.
     */
    private function writeGithubFiles(string $markdown, int $stale): void
    {
        $summary = getenv('GITHUB_STEP_SUMMARY');
        $output = getenv('GITHUB_OUTPUT');

        if (is_string($summary) && $summary !== '') {
            @file_put_contents($summary, $markdown."\n", FILE_APPEND);
        }

        if (is_string($output) && $output !== '') {
            @file_put_contents($output, "stale={$stale}\n", FILE_APPEND);
        }
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
