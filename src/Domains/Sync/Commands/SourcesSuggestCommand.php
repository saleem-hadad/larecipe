<?php

namespace LaRecipe\Domains\Sync\Commands;

use Illuminate\Console\Command;
use LaRecipe\Domains\Sync\Actions\ApplySourcesAction;
use LaRecipe\Domains\Sync\Actions\SuggestSourcesAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\Contracts\Workspace;
use LaRecipe\Domains\Sync\DTOs\SourceSuggestion;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `php artisan larecipe:sources:suggest`: proposes `sources:` for pages that
 * declare none, from the classes, commands and routes they mention.
 */
#[AsCommand(name: 'larecipe:sources:suggest')]
final class SourcesSuggestCommand extends Command
{
    protected $signature = 'larecipe:sources:suggest
        {--set= : Only this doc set}
        {--page= : Only this page path}
        {--all : Also suggest for pages that already declare sources}
        {--apply : Add the suggestions to pages that declare no sources yet}
        {--dry-run : With --apply, show what would change without writing}
        {--json : Print the suggestions as JSON}';

    protected $description = 'Suggest sources: for docs pages from the code they mention';

    public function handle(SuggestSourcesAction $suggest, ApplySourcesAction $apply, DocsCatalog $catalog, Workspace $workspace): int
    {
        $set = $this->option('set');

        if (is_string($set) && $catalog->set($set) === null) {
            $this->components->error("There is no doc set named [{$set}].");

            return self::INVALID;
        }

        $suggestions = $suggest->execute(
            is_string($set) ? $set : null,
            is_string($this->option('page')) ? $this->option('page') : null,
            (bool) $this->option('all'),
        );

        if ($this->option('json')) {
            $this->output->writeln((string) json_encode(array_map(fn (SourceSuggestion $s) => $s->toArray(), $suggestions), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), OutputInterface::OUTPUT_RAW);
        } else {
            $this->print($suggestions);
        }

        if ($this->option('apply') && $suggestions !== []) {
            $workspace = $workspace->dryRun((bool) $this->option('dry-run'));

            foreach ($apply->execute($workspace, $suggestions) as $change) {
                $this->components->twoColumnDetail($change->path, $change->status->value.($change->note ? " ({$change->note})" : ''));
            }
        }

        return self::SUCCESS;
    }

    /** @param list<SourceSuggestion> $suggestions */
    private function print(array $suggestions): void
    {
        if ($suggestions === []) {
            $this->components->info('No suggestions: every page either declares sources or mentions nothing defined in this project.');

            return;
        }

        $byPage = [];

        foreach ($suggestions as $suggestion) {
            $byPage[$suggestion->page->label()][] = $suggestion;
        }

        foreach ($byPage as $page => $items) {
            $this->line("<options=bold>{$page}</>");
            $this->line('  sources: '.json_encode(array_map(fn (SourceSuggestion $s) => $s->source, $items), JSON_UNESCAPED_SLASHES));

            foreach ($items as $item) {
                $this->line("    {$item->source}  <fg=gray>".implode(', ', $item->evidence).'</>');
            }

            $this->newLine();
        }

        $this->components->info('Add these to each page\'s frontmatter, or run again with --apply.');
    }
}
