<?php

namespace Workbench\App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Sources\Laravel\SourceManager;
use LaRecipe\Facades\LaRecipe;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use Workbench\App\Http\Controllers\FakeDocsServicesController;
use Workbench\App\Http\Controllers\SubscribeController;
use Workbench\App\Support\ProvenanceFixtureSource;
use Workbench\App\Support\TrustedFixtureHtml;

/**
 * The development app for working on LaRecipe itself: `composer serve` serves the
 * fixture doc sets so the UI can be developed against real pages.
 *
 * The "guide" set (tests/Fixtures/track-b) is the reading experience's
 * showcase: every component in its exact server HTML, provenance and tickets,
 * two versions, Arabic, the example Subscribe extension at article.after, and
 * a development stand-in for Ask (the workbench has no AI provider). Search
 * and feedback are LaRecipe's real ones.
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    /** The fixture set the UI is developed and browser-tested against. */
    public const SHOWCASE = 'track-b';

    public function register(): void
    {
        $fixtures = dirname(__DIR__, 3).'/tests/Fixtures';

        config()->set('larecipe.sets', [
            'docs' => [
                'route' => ['prefix' => 'docs', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => $fixtures.'/docs'],
                'access' => ['mode' => 'public'],
            ],
            'handbook' => [
                'route' => ['prefix' => 'handbook', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => $fixtures.'/handbook'],
                'access' => ['mode' => 'public'],
            ],
            self::SHOWCASE => [
                'route' => ['prefix' => 'guide', 'middleware' => ['web']],
                'source' => ['driver' => 'fixture', 'path' => $fixtures.'/track-b'],
                'access' => ['mode' => 'public'],
            ],
        ]);

        // Relative fixture dates ("2 hours ago") must not be frozen in a persistent cache.
        config()->set('larecipe.cache.store', 'array');

        $this->app->afterResolving(SourceManager::class, function (SourceManager $sources) {
            $sources->extend('fixture', fn ($app, SetConfig $set) => new ProvenanceFixtureSource(
                $set->key,
                (string) $set->source['path'],
                $app->make(FrontmatterParser::class),
            ));
        });

    }

    public function boot(): void
    {
        $this->loadViewsFrom(dirname(__DIR__, 2).'/resources/views', 'workbench');

        // Registered in boot, after LaRecipe's own domains, so the showcase's Ask
        // stand-in is the last word for its set.
        $this->app->afterResolving(PagePresenter::class, function (PagePresenter $presenter) {
            $presenter->extend(fn (DocSet $set, array $payload) => $this->showcaseServices($set, $payload));
        });

        // The showcase writes component HTML directly (see TrustedFixtureHtml).
        LaRecipe::extendMarkdown(function ($environment, $state) {
            $environment->addRenderer(HtmlBlock::class, new TrustedFixtureHtml($state, self::SHOWCASE), 100);
            $environment->addRenderer(HtmlInline::class, new TrustedFixtureHtml($state, self::SHOWCASE), 100);
        });

        // The example extension (ARCHITECTURE.md 9.5): the PHP side of <Subscribe />.
        LaRecipe::component('Subscribe')
            ->props(['list' => ['type' => 'string', 'default' => 'larecipe']])
            ->fallback(fn (array $props) => view('workbench::subscribe', $props))
            ->markdown(fn (array $props) => 'Subscribe to the changelog: '.url('/newsletter'))
            ->island()
            ->description('A newsletter signup form.');

        // The extension is built by @larecipe/vite into workbench/public/build/larecipe.
        $build = dirname(__DIR__, 2).'/public/build/larecipe';
        $this->app->make(Assets::class)->useExtension($build, '/build/larecipe');

        Route::middleware('web')->group(function () use ($build) {
            Route::post('/newsletter/subscribe', SubscribeController::class)->middleware('throttle:30,1');
            Route::view('/newsletter', 'workbench::newsletter');

            Route::post('/_workbench/ask', [FakeDocsServicesController::class, 'ask']);

            Route::get('/build/larecipe/{file}', function (string $file) use ($build) {
                $path = realpath($build.'/'.$file);
                abort_if($path === false || ! str_starts_with($path, realpath($build) ?: $build) || ! is_file($path), 404);

                return response()->file($path, [
                    'Content-Type' => match (pathinfo($path, PATHINFO_EXTENSION)) {
                        'js' => 'text/javascript; charset=UTF-8',
                        'css' => 'text/css; charset=UTF-8',
                        default => 'application/octet-stream',
                    },
                ]);
            })->where('file', '.*');
        });
    }

    /**
     * Boot payload additions for the showcase set only: Ask in the contract
     * the Ask domain uses, plus the ticket details (number, summary, pages,
     * lines) a git provenance resolver would add.
     */
    private function showcaseServices(DocSet $set, array $payload): array
    {
        if ($set->key !== self::SHOWCASE) {
            return [];
        }

        $locale = $payload['locale']['code'];
        $additions = [
            'features' => ['ask' => true],
            'ask' => ['enabled' => true, 'endpoint' => '/_workbench/ask'],
        ];

        $source = $this->app->make(SourceManager::class)->for($set->key);
        $entry = $source instanceof ProvenanceFixtureSource
            ? $source->entry(new PageRef($set->key, $payload['version'], $locale, $payload['page']['path']))
            : null;

        if ($entry !== null && $payload['page']['provenance'] !== null) {
            $additions['page']['provenance'] = array_intersect_key($entry, array_flip(['ticket', 'summary', 'pages', 'additions', 'deletions']));
        }

        return $additions;
    }
}
