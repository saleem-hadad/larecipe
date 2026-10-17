<?php

namespace LaRecipe\Domains\Export\Commands;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\RateLimiter;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\Laravel\RouteRegistrar;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Export\Actions\ExportSiteAction;
use LaRecipe\Domains\Export\DTOs\ExportOptions;
use LaRecipe\Domains\Export\Laravel\KernelSiteClient;
use LaRecipe\Domains\Sources\Actions\ListLocalizedPagesAction;

/**
 * php artisan larecipe:export --out=build/docs [--set=docs] [--base-url=https://docs.acme.dev]
 *
 * Writes public doc sets as a static site that works offline, search included.
 * Everything that needs the running app is left out and listed.
 */
final class ExportCommand extends Command
{
    protected $signature = 'larecipe:export
        {--out=build/docs : The folder to write the site into}
        {--set=* : The doc sets to export (all public sets by default)}
        {--base-url= : Where the site will be served from, for example https://docs.acme.dev}';

    protected $description = 'Export public doc sets as a static site';

    public function handle(SetRegistry $registry, ListLocalizedPagesAction $pages): int
    {
        $keys = array_filter((array) $this->option('set'));
        $unknown = array_diff($keys, array_keys($registry->configs()));

        if ($unknown !== []) {
            $this->components->error('There is no doc set named '.implode(', ', $unknown).'. Known sets: '.implode(', ', array_keys($registry->configs())).'.');

            return self::FAILURE;
        }

        $baseUrl = is_string($this->option('base-url')) && $this->option('base-url') !== '' ? rtrim($this->option('base-url'), '/') : null;
        $origin = $this->origin($baseUrl);
        $out = $this->absolute((string) $this->option('out'));

        // Each page is a request; nothing about it needs a stored session.
        $this->laravel['config']->set('session.driver', 'array');

        // The export reads every docs asset in process, from one address: the reader rate limit does not apply.
        RateLimiter::for(RouteRegistrar::ASSET_LIMITER, fn () => Limit::none());

        $client = new KernelSiteClient($this->laravel->make(Kernel::class), $this->laravel->make(DocsUrls::class), $this->laravel->make(Assets::class), $origin, $this->laravel->publicPath());
        $sets = array_map(fn (string $key) => $registry->get($key), $keys !== [] ? array_values($keys) : array_keys($registry->configs()));

        $result = (new ExportSiteAction($client, $pages))->execute(new ExportOptions($sets, $out, $baseUrl));

        foreach ($result->sets as $key => $counts) {
            $this->components->twoColumnDetail($key, "{$counts['pages']} pages, {$counts['markdown']} Markdown twins");
        }

        $this->newLine();
        $this->line('  Left out of the static site:');

        foreach ($result->leftOut as $line) {
            $this->line("    - {$line}");
        }

        $this->newLine();

        if ($baseUrl === null) {
            $this->components->warn("No --base-url: canonical and absolute links point at {$origin}.");
        }

        $this->components->info('Wrote '.count($result->files)." files to {$out}.");

        return self::SUCCESS;
    }

    private function origin(?string $baseUrl): string
    {
        $url = $baseUrl ?? (string) $this->laravel['config']->get('app.url', 'http://localhost');
        $parts = parse_url($url) ?: [];

        return ($parts['scheme'] ?? 'http').'://'.($parts['host'] ?? 'localhost').(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    private function absolute(string $path): string
    {
        return str_starts_with($path, '/') ? rtrim($path, '/') : $this->laravel->basePath(rtrim($path, '/'));
    }
}
