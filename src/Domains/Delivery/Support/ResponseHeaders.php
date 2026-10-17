<?php

namespace LaRecipe\Domains\Delivery\Support;

use Illuminate\Http\Request;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers every docs response carries: content negotiation (Vary), the
 * agent surfaces (Link), validators (ETag, Last-Modified) and caching rules.
 */
final class ResponseHeaders
{
    public function __construct(
        private readonly DocsUrls $urls,
        private readonly PageAccess $access,
        private readonly Assets $assets,
    ) {}

    /**
     * @param  bool  $perRequest  the body carries something personal to this session (a CSRF
     *                            token in a component's fallback form), decided before it was filled in
     */
    public function apply(Response $response, DocSet $set, Page $page, Request $request, string $format, bool $perRequest = false): Response
    {
        $links = ['<'.$this->urls->page($set, $page->ref, true).'>; rel="alternate"; type="text/markdown"'];

        if ($set->config->setting('agents.llms_txt', true)) {
            $links[] = '<'.$this->urls->llms($set, $set->isVersioned() ? $page->ref->version : null).'>; rel="describedby"; type="text/plain"';
        }

        if ($set->config->setting('mcp.web.enabled', true)) {
            $links[] = '<'.$this->urls->mcp($set).'>; rel="mcp"';
        }

        if ($set->config->setting('agents.skill_md', true)) {
            $links[] = '<'.$this->urls->skill($set).'>; rel="describedby"; type="text/markdown"; title="skill"';
        }

        $response->headers->set('Vary', 'Accept');
        $response->headers->set('Link', implode(', ', $links));
        $response->headers->set('X-LaRecipe-Build', $this->assets->build());
        $response->setEtag(substr(hash('xxh128', $page->contentHash.'|'.$this->assets->build().'|'.$format.'|'.$page->ref->key()), 0, 20));

        if ($page->updatedAt !== null) {
            $response->setLastModified($page->updatedAt);
        }

        $perRequest = $perRequest || ($format === 'html' && $this->access->user() !== null);
        $sharedByEveryone = $this->access->isPublic($set) && $page->frontmatter->visibility()->value !== 'private';

        if ($sharedByEveryone && ! $perRequest) {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=600');
        } elseif ($sharedByEveryone) {
            // A public page with a session token: never stored by a shared cache, still indexable.
            $response->headers->set('Cache-Control', 'private, no-cache');
        } else {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        if ($page->frontmatter->noindex()) {
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        $response->isNotModified($request);

        return $response;
    }
}
