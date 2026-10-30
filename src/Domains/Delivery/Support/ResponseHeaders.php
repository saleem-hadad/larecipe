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
     * @param  string  $references  the fingerprint of the app surface the page's live references read
     *                              (routes, commands...): the body changes with it while the source does not
     */
    public function apply(Response $response, DocSet $set, Page $page, Request $request, string $format, bool $perRequest = false, string $references = ''): Response
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

        // A signed-in reader's response can list pages guests never see (in the
        // navigation of every format), so it is personal: never publicly cached,
        // and its ETag never matches the guest's.
        $reader = $this->reader();
        $perRequest = $perRequest || $reader !== 'guest';

        $response->headers->set('Vary', $perRequest ? 'Accept, Cookie' : 'Accept');
        $response->headers->set('Link', implode(', ', $links));
        $response->headers->set('X-LaRecipe-Build', $this->assets->build());
        $response->setEtag(substr(hash('xxh128', $page->contentHash.'|'.$this->assets->build().'|'.$format.'|'.$page->ref->key().'|'.$reader.'|'.$references), 0, 20));

        if ($page->updatedAt !== null) {
            $response->setLastModified($page->updatedAt);
        }
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

    /** Who the response is for, for the ETag: "guest", or a hash of the signed-in reader. */
    private function reader(): string
    {
        $user = $this->access->user();

        if ($user === null) {
            return 'guest';
        }

        $id = method_exists($user, 'getAuthIdentifier') ? $user->getAuthIdentifier() : spl_object_id($user);

        return 'reader:'.hash('xxh128', $user::class.'|'.(is_scalar($id) ? (string) $id : ''));
    }
}
