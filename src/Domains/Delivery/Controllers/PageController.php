<?php

namespace LaRecipe\Domains\Delivery\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;
use LaRecipe\Domains\Access\Actions\FilterGatedSourceAction;
use LaRecipe\Domains\Access\Actions\ResolveAudienceAction;
use LaRecipe\Domains\Access\Actions\VerifyShareLinkAction;
use LaRecipe\Domains\Access\DTOs\Audience;
use LaRecipe\Domains\Access\DTOs\ReaderView;
use LaRecipe\Domains\Access\DTOs\ShareLink;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Delivery\DTOs\DocsRequest;
use LaRecipe\Domains\Delivery\Events\MarkdownServed;
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
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Domains\Markdown\Actions\RenderPageAction;
use LaRecipe\Domains\Markdown\DTOs\RenderedPage;
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
        private readonly SetFromRequest $current,
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
        private readonly ResolveAudienceAction $audiences,
        private readonly FilterGatedSourceAction $gatedSource,
        private readonly VerifyShareLinkAction $shareLinks,
    ) {}

    public function __invoke(Request $request): SymfonyResponse
    {
        // Read by name: route defaults (larecipeSet) are merged into the parameters,
        // so positional binding would hand the set key to an empty optional path.
        $path = (string) $request->route('path', '');
        $set = $this->current->set($request);
        $docs = $this->resolver->resolve($set, $path, $request);

        if ($docs->redirect !== null) {
            return redirect()->to($this->keepShareToken($docs->redirect, $request), 302);
        }

        // A share link (larecipe:share) opens exactly this one page for anyone holding it.
        $shared = $this->shareLinks->execute($request->query(ShareLink::QUERY), new PageRef($set->key, $docs->version, $docs->locale, $docs->path));

        if (! $shared && ! $this->access->canEnter($set)) {
            if ($set->config->accessMode === 'auth' && $this->access->user() === null) {
                throw new AuthenticationException;
            }

            return $this->notFound->respond($set, $docs, $request);
        }

        if ($set->version($docs->version) === null || (! $shared && ! $this->access->canViewVersion($set, $docs->version))) {
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
            return $this->redirectFor($set, $docs, $request) ?? $this->notFound->respond($set, $docs, $request);
        }

        if (! $shared && ! $this->access->canView($set, $summary)) {
            return $this->notFound->respond($set, $docs, $request);
        }

        $page = $this->pages->execute($summary->ref);

        if ($page === null) {
            return $this->notFound->respond($set, $docs, $request);
        }

        $viewed = new PageViewed($set->key, $page->ref, $docs->format, $request);
        event($viewed);

        if ($docs->isMarkdown()) {
            event(new MarkdownServed($set->key, $page->ref, $viewed->kind, $viewed->userAgent, $docs->explicitMarkdown));
        }

        // The guest render lists every ability the page's gate blocks ask about; the
        // reader's are evaluated once, and readers who hold the same ones share a render.
        $guest = $this->rendered($set, $page, Audience::guest());
        $audience = $guest->abilities === [] ? Audience::guest() : $this->audiences->execute($guest->abilities);
        $view = new ReaderView($audience, $guest->abilities !== [], $this->access->isPublicPage($set, $summary), $shared);
        $rendered = $audience->isGuest() ? $guest : $this->rendered($set, $page, $audience);

        // Navigation lists every page of the reader's locale, translated or falling back.
        $summaries = $this->catalog->localized($set, $docs->version, $fallbackFrom ?? $docs->locale);

        if ($fallbackFrom !== null) {
            $rendered = $this->inOriginalLanguage($rendered, $set);
        }

        return match ($docs->format) {
            DocsRequest::MARKDOWN => $this->markdownResponse($set, $page, $request, $view),
            DocsRequest::JSON => $this->jsonResponse($set, $page, $summaries, $request, $fallbackFrom, $rendered, $view),
            default => $this->htmlResponse($set, $page, $summaries, $request, $fallbackFrom, $rendered, $view),
        };
    }

    private function htmlResponse(DocSet $set, Page $page, array $summaries, Request $request, ?string $fallbackFrom, RenderedPage $rendered, ReaderView $view): SymfonyResponse
    {
        $payload = $this->presenter->present($set, $page, $rendered, $this->access->visible($set, $summaries), $fallbackFrom);

        $html = View::make('larecipe::page', [
            'set' => $set,
            'page' => $page,
            'rendered' => $rendered,
            'boot' => $payload,
            'assets' => $this->assets,
        ])->render();

        // Decide before substituting: a page carrying a CSRF token is personal to the session.
        $perRequest = str_contains($html, LaRecipe::CSRF_FIELD);
        $html = $this->withCsrfToken($html);

        return $this->finish(new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']), $set, $page, $request, 'html', $view, $perRequest);
    }

    private function jsonResponse(DocSet $set, Page $page, array $summaries, Request $request, ?string $fallbackFrom, RenderedPage $rendered, ReaderView $view): SymfonyResponse
    {
        $payload = $this->presenter->present($set, $page, $rendered, $this->access->visible($set, $summaries), $fallbackFrom);
        $json = $this->presenter->json($payload, $rendered);
        $perRequest = str_contains($json['page']['html'], LaRecipe::CSRF_FIELD);
        $json['page']['html'] = $this->withCsrfToken($json['page']['html']);

        return $this->finish(new JsonResponse($json, 200, ['Content-Type' => RequestResolver::JSON_TYPE]), $set, $page, $request, 'json', $view, $perRequest);
    }

    private function markdownResponse(DocSet $set, Page $page, Request $request, ReaderView $view): SymfonyResponse
    {
        // Agent Markdown starts from the page source, so it gets the reader's view of
        // the gate blocks, decided by the same AST filter as the HTML.
        if ($view->gated) {
            $page = $this->gatedSource->execute($page, $view->audience);
        }

        $body = $this->markdown->render($page, [
            'url' => $this->urls->page($set, $page->ref),
            'version' => $set->isVersioned() ? $page->ref->version : null,
            'locale' => $set->isMultilingual() ? $page->ref->locale : null,
            'llms' => $set->config->setting('agents.llms_txt', true) ? $this->urls->llms($set, $set->isVersioned() ? $page->ref->version : null) : null,
            'mcp' => $set->config->setting('mcp.web.enabled', true) ? $this->urls->mcp($set) : null,
            'skill' => $set->config->setting('agents.skill_md', true) ? $this->urls->skill($set) : null,
        ], new DocsLinkResolver($set, $this->urls, $this->sources->for($set->key), markdownTwins: true), $view->audience->toContext());

        return $this->finish(new Response($body, 200, ['Content-Type' => 'text/markdown; charset=UTF-8']), $set, $page, $request, 'markdown', $view);
    }

    /** One cached render per audience variant (the abilities on the page), never per user. */
    private function rendered(DocSet $set, Page $page, Audience $audience): RenderedPage
    {
        $variant = $set->config->prefix.'|'.$set->config->domain.($audience->isGuest() ? '' : '|audience:'.$audience->key());

        return $this->cache->remember($page, $variant, fn () => $this->render->execute(
            $page,
            new DocsLinkResolver($set, $this->urls, $this->sources->for($set->key)),
            $audience->toContext(),
        ));
    }

    /**
     * The shared response headers, then the access rules on top: a response meant
     * for one reader is never stored by a shared cache, never answers a conditional
     * request with another variant, and is never indexed.
     */
    private function finish(SymfonyResponse $response, DocSet $set, Page $page, Request $request, string $format, ReaderView $view, bool $perRequest = false): SymfonyResponse
    {
        if ($view->isPersonal()) {
            $request->headers->remove('If-None-Match');
            $request->headers->remove('If-Modified-Since');
        }

        $response = $this->headers->apply($response, $set, $page, $request, $format, $perRequest);

        if ($view->isPersonal()) {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex');
            $response->headers->remove('ETag');
        } elseif ($view->gated) {
            $response->headers->set('Vary', 'Accept, Cookie');
        }

        if ($view->shared) {
            // The token is in the URL: never hand it to the next site in a Referer header.
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }

    /**
     * Fills the session's CSRF token into every field LaRecipe::csrfField()
     * emitted. Only that exact field: the placeholder anywhere else (written
     * by an author, in a link or an image URL) stays as it is.
     */
    private function withCsrfToken(string $html): string
    {
        return str_replace(LaRecipe::CSRF_FIELD, '<input type="hidden" name="_token" value="'.e(csrf_token() ?? '').'">', $html);
    }

    /** A fallback article keeps its own language and direction inside the reader's chrome. */
    private function inOriginalLanguage(RenderedPage $rendered, DocSet $set): RenderedPage
    {
        $original = $set->defaultLocale();

        return RenderedPage::fromArray([
            'html' => '<div class="lr-fallback-content" lang="'.e($original->code).'" dir="'.$original->dir->value.'">'.$rendered->html.'</div>',
        ] + $rendered->toArray());
    }

    /** An unversioned share link redirects to the versioned URL with its token intact. */
    private function keepShareToken(string $target, Request $request): string
    {
        $token = $request->query(ShareLink::QUERY);

        return is_string($token) && $token !== ''
            ? $target.(str_contains($target, '?') ? '&' : '?').ShareLink::QUERY.'='.rawurlencode($token)
            : $target;
    }

    /**
     * redirect_from in frontmatter and redirects in larecipe.json keep old URLs alive.
     *
     * On a versioned set, a redirect whose "from" has no version applies in every
     * version: /docs/setup matches /docs/2.x/setup, and a target without a version
     * stays in the reader's version. Only pages the reader may see are searched
     * for redirect_from, so a redirect never reveals a private page's URL.
     */
    private function redirectFor(DocSet $set, DocsRequest $docs, Request $request): ?SymfonyResponse
    {
        $requested = '/'.trim($request->path(), '/');
        $unversioned = $set->isVersioned() ? $this->withoutVersion($set, $docs) : null;

        foreach ($set->manifest->redirects() as $redirect) {
            $from = rtrim($redirect['from'], '/');

            if ($from === rtrim($requested, '/')) {
                return redirect()->to($redirect['to'], $redirect['status']);
            }

            if ($from === $unversioned) {
                return redirect()->to($this->inVersion($set, $redirect['to'], $docs->version), $redirect['status']);
            }
        }

        foreach ($this->catalog->summaries($set, $docs->version, $docs->locale) as $summary) {
            if (! $this->access->canView($set, $summary)) {
                continue;
            }

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

    /** The requested page's URL path without its version segment: /docs/3.x/setup becomes /docs/setup. */
    private function withoutVersion(DocSet $set, DocsRequest $docs): string
    {
        $locale = $set->isMultilingual() && $docs->locale !== $set->defaultLocale()->code ? $docs->locale : '';
        $segments = [trim($set->config->prefix, '/'), $locale, $docs->path === 'index' ? '' : $docs->path];

        return '/'.implode('/', array_filter($segments, fn (string $s) => $s !== ''));
    }

    /** A redirect target inside this set that names no version, moved into the reader's version. */
    private function inVersion(DocSet $set, string $target, string $version): string
    {
        $prefix = trim($set->config->prefix, '/');
        $path = trim((string) parse_url($target, PHP_URL_PATH), '/');
        $inSet = $prefix === '' || $path === $prefix || str_starts_with($path, $prefix.'/');

        if (! str_starts_with($target, '/') || str_starts_with($target, '//') || ! $inSet) {
            return $target;
        }

        $segments = array_values(array_filter(explode('/', substr($path, strlen($prefix))), fn (string $s) => $s !== ''));
        $locale = $segments !== [] && $set->isMultilingual() && $set->locale($segments[0]) !== null ? array_shift($segments) : '';

        if ($segments !== [] && $set->version($segments[0]) !== null) {
            return $target;
        }

        $rest = substr($target, strlen('/'.$path));

        return '/'.implode('/', array_filter([$prefix, $locale, $version, ...$segments], fn (string $s) => $s !== '')).$rest;
    }
}
