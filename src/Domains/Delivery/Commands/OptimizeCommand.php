<?php

namespace LaRecipe\Domains\Delivery\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Delivery\Services\RenderCache;
use LaRecipe\Domains\Delivery\Support\DocsLinkResolver;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Search\Actions\BuildClientIndexAction;
use LaRecipe\Domains\Search\Actions\LoadSearchIndexAction;
use LaRecipe\Domains\Search\Actions\ResolveAudienceAction;
use LaRecipe\Domains\Search\DTOs\ClientIndex;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Compiles every repository doc set ahead of the first request, like
 * route:cache: page lists and navigation, every rendered page and the search
 * records, into LaRecipe's cache store. Runs as part of `php artisan optimize`,
 * so existing deploy scripts pick it up.
 *
 * Caches are content-addressed, so a deploy with changed docs simply compiles
 * new entries. What was compiled is listed in bootstrap/cache/larecipe/, which
 * larecipe:clear reads to forget it again. Database sets are skipped: they are
 * cached as they publish.
 */
#[AsCommand(name: 'larecipe:optimize')]
final class OptimizeCommand extends Command
{
    public const MANIFEST = 'cache/larecipe/optimized.php';

    protected $signature = 'larecipe:optimize {--set=* : Only compile these doc sets}';

    protected $description = 'Compile LaRecipe pages, navigation and search records into the cache';

    public function handle(
        SetRegistry $sets,
        PageCatalog $catalog,
        RenderCache $cache,
        RenderPageAction $render,
        GetPageAction $pages,
        LoadSearchIndexAction $search,
        ResolveAudienceAction $audiences,
        BuildClientIndexAction $clientIndex,
        PageAccess $access,
        DocsUrls $urls,
        SourceResolver $sources,
        Filesystem $files,
    ): int {
        $only = (array) $this->option('set');
        $manifest = ['compiled_at' => date(DATE_ATOM), 'sets' => [], 'render_keys' => []];

        foreach ($sets->all() as $set) {
            if ($only !== [] && ! in_array($set->key, $only, true)) {
                continue;
            }

            if ($set->config->driver !== 'repository') {
                $this->components->twoColumnDetail($set->key, '<fg=gray>skipped: cached as it publishes</>');

                continue;
            }

            $links = new DocsLinkResolver($set, $urls, $sources->for($set->key));
            // The same variant PageController renders with, so requests hit these entries.
            $variant = $set->config->prefix.'|'.$set->config->domain;

            foreach ($set->versions() as $version) {
                foreach ($set->locales() as $locale) {
                    $summaries = $catalog->summaries($set, $version->id, $locale->code);

                    if ($summaries === []) {
                        continue;
                    }

                    $catalog->navigation($set, $version->id, $locale->code, $summaries);
                    $errors = 0;

                    foreach ($summaries as $summary) {
                        $page = $pages->execute($summary->ref);

                        if ($page === null) {
                            continue;
                        }

                        $rendered = $cache->remember($page, $variant, fn () => $render->execute($page, $links));
                        $manifest['render_keys'][] = $cache->key($page, $variant);

                        if ($rendered->hasErrors()) {
                            $errors++;
                        }
                    }

                    $records = count($search->execute($set->key, $version->id, $locale->code)->records);
                    $index = $this->guestIndex($set, $version->id, $locale->code, $summaries, $access, $audiences, $clientIndex, $urls);

                    $manifest['sets'][$set->key][$version->id][$locale->code] = [
                        'pages' => count($summaries),
                        'records' => $records,
                        'index_kb' => (int) ceil($index->gzipBytes / 1024),
                    ];

                    $this->report($set, $version->id, $locale->code, count($summaries), $records, $errors, $index);
                }
            }
        }

        $path = $this->laravel->bootstrapPath(self::MANIFEST);
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, '<?php return '.var_export($manifest, true).';'.PHP_EOL);

        $this->components->info('LaRecipe docs compiled.');

        return self::SUCCESS;
    }

    /** The index guests download, measured against the size budget. */
    private function guestIndex(DocSet $set, string $version, string $locale, array $summaries, PageAccess $access, ResolveAudienceAction $audiences, BuildClientIndexAction $clientIndex, DocsUrls $urls): ClientIndex
    {
        $visible = array_map('strval', array_keys($access->visible($set, $summaries)));
        $audience = $audiences->execute($set->key, $version, $locale, $visible, fn () => false);

        return $clientIndex->execute(
            $set->key,
            $version,
            $locale,
            $audience,
            fn (string $path, ?string $anchor) => $urls->pagePath($set, new PageRef($set->key, $version, $locale, $path)),
            (int) $set->config->setting('search.budget_kb', ClientIndex::BUDGET_KB),
        );
    }

    private function report(DocSet $set, string $version, string $locale, int $pages, int $records, int $errors, ClientIndex $index): void
    {
        $scope = implode(' ', array_filter([$set->key, $set->isVersioned() ? $version : null, $set->isMultilingual() ? $locale : null]));

        $this->components->twoColumnDetail($scope, sprintf(
            '%d %s, %d search %s, %d KB index',
            $pages,
            $pages === 1 ? 'page' : 'pages',
            $records,
            $records === 1 ? 'record' : 'records',
            (int) ceil($index->gzipBytes / 1024),
        ));

        if ($errors > 0) {
            $this->components->warn("{$scope}: {$errors} page(s) have render errors and stay uncached. Run php artisan larecipe:validate.");
        }

        foreach ($index->issues as $issue) {
            $this->components->warn("{$scope}: {$issue['message']}");
        }
    }
}
