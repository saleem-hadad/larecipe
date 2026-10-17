<?php

namespace LaRecipe\Domains\Delivery\Services;

use Illuminate\Http\Request;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\DTOs\DocsRequest;

/**
 * Reads a docs URL: which locale, which version, which page, and which
 * representation (HTML, agent Markdown or the client navigation JSON).
 *
 * Markdown wins with an explicit ".md" suffix, or when the Accept header ranks
 * text/markdown above text/html. Unversioned URLs on a versioned set redirect
 * to the default version, so links written before a second version existed
 * never break.
 */
final class RequestResolver
{
    public const JSON_TYPE = 'application/vnd.larecipe.page+json';

    public function __construct(private readonly DocsUrls $urls) {}

    public function resolve(DocSet $set, string $path, Request $request): DocsRequest
    {
        $path = trim(rawurldecode($path), '/');
        $explicitMarkdown = false;

        if (str_ends_with(strtolower($path), '.md')) {
            $explicitMarkdown = true;
            $path = substr($path, 0, -3);
        }

        $segments = $path === '' ? [] : explode('/', $path);
        $locale = $set->defaultLocale()->code;

        if ($segments !== [] && $set->isMultilingual() && $segments[0] !== $locale && $set->locale($segments[0]) !== null) {
            $locale = array_shift($segments);
        }

        $format = $explicitMarkdown ? DocsRequest::MARKDOWN : $this->negotiate($request);
        $version = $set->defaultVersion()->id;

        if ($set->isVersioned()) {
            if ($segments !== [] && $set->version($segments[0]) !== null) {
                $version = array_shift($segments);
            } else {
                $target = new PageRef($set->key, $version, $locale, PageRef::normalizePath(implode('/', $segments) ?: $set->landingPath()));

                if ($segments === [] && $set->config->isRootMounted()) {
                    return new DocsRequest($set->key, $locale, $version, $set->landingPath(), $format, null, $explicitMarkdown);
                }

                return new DocsRequest($set->key, $locale, $version, $target->path, $format, $this->urls->page($set, $target, $explicitMarkdown), $explicitMarkdown);
            }
        }

        $pagePath = $segments === [] ? $set->landingPath() : PageRef::normalizePath(implode('/', $segments));

        if ($explicitMarkdown && $pagePath === 'index' && $segments !== [] && end($segments) === 'index') {
            $pagePath = 'index';
        }

        return new DocsRequest($set->key, $locale, $version, $pagePath, $format, null, $explicitMarkdown);
    }

    private function negotiate(Request $request): string
    {
        foreach ($request->getAcceptableContentTypes() as $type) {
            $type = strtolower($type);

            if ($type === 'text/markdown' || $type === 'text/x-markdown') {
                return DocsRequest::MARKDOWN;
            }

            if ($type === self::JSON_TYPE) {
                return DocsRequest::JSON;
            }

            if ($type === 'text/html' || $type === 'application/xhtml+xml' || $type === '*/*') {
                return DocsRequest::HTML;
            }
        }

        return DocsRequest::HTML;
    }
}
