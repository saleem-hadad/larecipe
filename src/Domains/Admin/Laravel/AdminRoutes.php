<?php

namespace LaRecipe\Domains\Admin\Laravel;

use Illuminate\Routing\Router;
use LaRecipe\Domains\Admin\Controllers\AssetController;
use LaRecipe\Domains\Admin\Controllers\ManifestController;
use LaRecipe\Domains\Admin\Controllers\NavigationController;
use LaRecipe\Domains\Admin\Controllers\OverviewController;
use LaRecipe\Domains\Admin\Controllers\PageController;
use LaRecipe\Domains\Admin\Controllers\PreviewController;
use LaRecipe\Domains\Admin\Controllers\ReviewController;
use LaRecipe\Domains\Admin\Controllers\RevisionController;
use LaRecipe\Domains\Admin\Controllers\ShellController;
use LaRecipe\Domains\Content\DTOs\SetConfig;

/**
 * The admin routes of one doc set, all under /{prefix}/_admin and named
 * "larecipe.{set}.admin.*" (the set group adds the first part), so
 * SetFromRequest finds the set without anything else.
 *
 * Everything runs on the web guard with sessions and CSRF ('web' is added in
 * case the set's own middleware leaves it out), behind the manageLarecipe
 * gate. The API is declared before the shell's catch-all so a client-side
 * route never swallows it, and an unknown API path answers JSON, not HTML.
 */
final class AdminRoutes
{
    public function register(Router $router, SetConfig $set): void
    {
        $router->prefix('_admin')
            ->name('admin.')
            ->middleware(['web', AuthorizeAdmin::class])
            ->group(function (Router $router) {
                $router->prefix('api')->name('api.')->group(function (Router $router) {
                    // Every set: the overview and the agent-readiness card.
                    $router->get('overview', [OverviewController::class, 'overview'])->name('overview');
                    $router->get('readiness', [OverviewController::class, 'readiness'])->name('readiness');
                    $router->get('stale', [OverviewController::class, 'stale'])->name('stale');

                    // Database sets only: a repository set is edited in git and reviewed in its pull request.
                    $router->middleware(RequireDatabaseSet::class)->group(function (Router $router) {
                        $router->get('tree', [NavigationController::class, 'tree'])->name('tree');
                        $router->put('navigation', [NavigationController::class, 'update'])->name('navigation.update');

                        $router->get('page', [PageController::class, 'show'])->name('page.show');
                        $router->post('page', [PageController::class, 'store'])->name('page.store');
                        $router->put('page', [PageController::class, 'update'])->name('page.update');
                        $router->post('page/move', [PageController::class, 'move'])->name('page.move');
                        $router->delete('page', [PageController::class, 'destroy'])->name('page.destroy');

                        $router->post('preview', [PreviewController::class, 'preview'])->name('preview');
                        $router->post('validate', [PreviewController::class, 'validate'])->name('validate');

                        $router->get('manifest', [ManifestController::class, 'show'])->name('manifest.show');
                        $router->put('manifest', [ManifestController::class, 'update'])->name('manifest.update');

                        $router->get('review', [ReviewController::class, 'index'])->name('review.index');
                        $router->get('review/{revision}', [ReviewController::class, 'show'])->whereNumber('revision')->name('review.show');
                        $router->post('review/{revision}/publish', [ReviewController::class, 'publish'])->whereNumber('revision')->name('review.publish');
                        $router->post('review/{revision}/reject', [ReviewController::class, 'reject'])->whereNumber('revision')->name('review.reject');
                        $router->post('review/{revision}/edit', [ReviewController::class, 'edit'])->whereNumber('revision')->name('review.edit');

                        $router->get('revisions', [RevisionController::class, 'index'])->name('revisions.index');
                        $router->get('revisions/{revision}', [RevisionController::class, 'show'])->whereNumber('revision')->name('revisions.show');
                        $router->post('revisions/{revision}/revert', [RevisionController::class, 'revert'])->whereNumber('revision')->name('revisions.revert');

                        $router->get('assets', [AssetController::class, 'index'])->name('assets.index');
                        $router->post('assets', [AssetController::class, 'store'])->name('assets.store');
                        $router->delete('assets', [AssetController::class, 'destroy'])->name('assets.destroy');
                    });

                    $router->any('{any}', [ShellController::class, 'missing'])->where('any', '.*')->name('missing');
                });

                $router->get('{any?}', ShellController::class)->where('any', '.*')->name('shell');
            });
    }
}
