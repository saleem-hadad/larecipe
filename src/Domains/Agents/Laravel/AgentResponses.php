<?php

namespace LaRecipe\Domains\Agents\Laravel;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Headers for agent files, matching the page responses: shared output is
 * cached briefly in public (so CDNs help and edits still show up within
 * minutes), anything built for one caller is private and never indexed.
 */
final class AgentResponses
{
    public const TEXT = 'text/plain; charset=UTF-8';

    public const MARKDOWN = 'text/markdown; charset=UTF-8';

    public function make(string $body, string $contentType, bool $shared, Request $request): SymfonyResponse
    {
        $response = new Response($body, 200, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $this->cache($response, $shared);
        $response->setEtag(substr(hash('xxh128', $body), 0, 20));
        $response->isNotModified($request);

        return $response;
    }

    public function cache(SymfonyResponse $response, bool $shared): SymfonyResponse
    {
        if ($shared) {
            $response->headers->set('Cache-Control', 'public, max-age=60, s-maxage=600');
        } else {
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('X-Robots-Tag', 'noindex');
        }

        return $response;
    }

    public function notFound(string $what = 'This file'): SymfonyResponse
    {
        return new Response($what." was not found.\n", 404, [
            'Content-Type' => self::TEXT,
            'Cache-Control' => 'no-store',
        ]);
    }
}
