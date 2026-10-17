<?php

namespace LaRecipe\Domains\Delivery\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\DTOs\DocsRequest;
use LaRecipe\Domains\Delivery\Events\PageViewed;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Services\PageCatalog;
use LaRecipe\Domains\Delivery\Services\PagePresenter;
use LaRecipe\Domains\Delivery\Services\RenderCache;
use LaRecipe\Domains\Delivery\Services\RequestResolver;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Delivery\Support\DocsLinkResolver;
use LaRecipe\Domains\Delivery\Support\NotFoundResponder;
use LaRecipe\Domains\Delivery\Support\ResponseHeaders;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Markdown\Services\AgentMarkdownRenderer;
use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\LaRecipe;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Serves every page of every doc set, as HTML, agent Markdown or navigation JSON.
 *
 * One controller and one access check for every representation, so a page is
 * either visible to a caller everywhere or nowhere (the Same-Rules Rule).
 */
final class PageController
{
    public function __construct(
        private readonly \LaRecipe\Domains\Delivery\Support\SetFromRequest $current,
        private readonly SetRegistry $sets,
        private readonly RequestResolver $resolver,
        private readonly PageCatalog $catalog,
        private readonly PageAccess $access,
        private readonly GetPageAction $pages,
        private readonly RenderPageAction $render,
        private readonly RenderCache $cache,
        private readonly PagePresenter $presenter,
        private readonly AgentMarkdownRenderer $markdown,
        private readonly DocsUrls $urls,
        private readonly SourceResolver $sources,
        private readonly Assets $assets,
        private readonly ResponseHeaders $headers,
        private readonly NotFoundResponder $notFound,
    ) {}

    public function __invoke(Request $request): SymfonyResponse
    {
        // Read by name: route defaults (larecipeSet) are merged into the parameters,
        // so positional binding would hand the set key to an empty optional path.
        $path = (string) $request->route('path', '');
        $set = $this->current->set($request);
        $docs = $this->resolver->resolve($set, $path, $request);

        if ($docs->redirect !== null) {
            return redirect()->to($docs->redirect, 302);
        }

        if (! $this->access->canEnter($set)) {
            if ($set->config->accessMode === 'auth' && $this->access->user() === null) {
                throw new AuthenticationException;
            }

            return $this->notFound->respond($set, $docs, $request);
        }

        if ($set->version($docs->version) === null || ! $this->access->canViewVersion($set, $docs->version)) {
            return $this->notFound->respond($set, $docs, $request);
        }

        $summaries = $this->catalog->summaries($set, $docs->version, $docs->locale);
        $fallbackFrom = null;

        if (! isset($summaries[$docs->path]) && $docs->locale !== $set->defaultLocale()->code) {
            $default = $this->catalog->summaries($set, $docs->version, $set->defaultLocale()->code);

            if (isset($default[$docs->path])) {
                $fallbackFrom = $docs->locale;
                $docs = new DocsRequest($docs->set, $set->defaultLocale()->code, $docs->version, $docs->path, $docs->format, null, $docs->explicitMarkdown);
                $summaries = $default;
            }
        }

        $summary = $summaries[$docs->path] ?? null;

        if ($summary === null) {
            return $this->redirectFor($set, $docs) ?? $this->notFound->respond($set, $docs, $request);
        }

        if (! $this->access->canView($set, $summary)) {
            return $this->notFound->respond($set, $docs, $request);
        }

        $page = $this->pages->execute($summary->ref);

        if ($page === null) {
            return $this->notFound->respond($set, $docs, $request);
        }

        event(new PageViewed($set->key, $page->ref, $docs->format, $request));

        return match ($docs->format) {
            DocsRequest::MARKDOWN => $this->markdownResponse($set, $page, $request),
            DocsRequest::JSON => $this->jsonResponse($set, $page, $summaries, $request, $fallbackFrom),
            default => $this->htmlResponse($set, $page, $summaries, $request, $fallbackFrom),
        };
    }

    private function htmlResponse(DocSet $set, Page $page, array $summaries, Request $request, ?string $fallbackFrom): SymfonyResponse
    {
        $rendered = $this->rendered($set, $page);
        $payload = $this->presenter->present($set, $page, $rendered, $this->access->visible($set, $summaries), $fallbackFrom);

        $html = View::make('larecipe::page', [
            'set' => $set,
            'page' => $page,
            'rendered' => $rendered,
            'boot' => $payload,
            'assets' => $this->assets,
        ])->render();

        $html = str_replace(LaRecipe::CSRF_PLACEHOLDER, e(csrf_token() ?? ''), $html);

        return $this->headers->apply(new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']), $set, $page, $request, 'html');
    }

    private function jsonResponse(DocSet $set, Page $page, array $summaries, Request $request, ?string $fallbackFrom): SymfonyResponse
    {
        $rendered = $this->rendered($set, $page);
        $payload = $this->presenter->present($set, $page, $rendered, $this->access->visible($set, $summaries), $fallbackFrom);
        $json = $this->presenter->json($payload, $rendered);
        $json['page']['html'] = str_replace(LaRecipe::CSRF_PLACEHOLDER, e(csrf_token() ?? ''), $json['page']['html']);

        return $this->headers->apply(new JsonResponse($json, 200, ['Content-Type' => RequestResolver::JSON_TYPE]), $set, $page, $request, 'json');
    }

    private function markdownResponse(DocSet $set, Page $page, Request $request): SymfonyResponse
    {
        $body = $this->markdown->render($page, [
            'url' => $this->urls->page($set, $page->ref),
            'version' => $set->isVersioned() ? $page->ref->version : null,
            'locale' => $set->isMultilingual() ? $page->ref->locale : null,
            'llms' => $set->config->setting('agents.llms_txt', true) ? $this->urls->llms($set, $set->isVersioned() ? $page->ref->version : null) : null,
            'mcp' => $set->config->setting('mcp.web.enabled', true) ? $this->urls->mcp($set) : null,
            'skill' => $set->config->setting('agents.skill_md', true) ? $this->urls->skill($set) : null,
        ]);

        return $this->headers->apply(new Response($body, 200, ['Content-Type' => 'text/markdown; charset=UTF-8']), $set, $page, $request, 'markdown');
    }

    private function rendered(DocSet $set, Page $page): \LaRecipe\Domains\Markdown\DTOs\RenderedPage
    {
        return $this->cache->remember($page, $set->config->prefix.'|'.$set->config->domain, fn () => $this->render->execute(
            $page,
            new DocsLinkResolver($set, $this->urls, $this->sources->for($set->key)),
        ));
    }

    /** redirect_from in frontmatter and redirects in larecipe.json keep old URLs alive. */
    private function redirectFor(DocSet $set, DocsRequest $docs): ?SymfonyResponse
    {
        $requested = '/'.trim(request()->path(), '/');

        foreach ($set->manifest->redirects() as $redirect) {
            if (rtrim($redirect['from'], '/') === rtrim($requested, '/')) {
                return redirect()->to($redirect['to'], $redirect['status']);
            }
        }

        foreach ($this->catalog->summaries($set, $docs->version, $docs->locale) as $summary) {
            $page = $this->pages->execute($summary->ref);

            foreach ($page?->frontmatter->redirectFrom() ?? [] as $from) {
                $from = '/'.trim($from, '/');

                if ($from === $requested || PageRef::normalizePath($from) === $docs->path) {
                    return redirect()->to($this->urls->page($set, $summary->ref, $docs->explicitMarkdown), 301);
                }
            }
        }

        return null;
    }
}
