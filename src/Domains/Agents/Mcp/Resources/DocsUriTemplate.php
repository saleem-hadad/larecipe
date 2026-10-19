<?php

namespace LaRecipe\Domains\Agents\Mcp\Resources;

use Laravel\Mcp\Support\UriTemplate;

/**
 * docs://{set}/{version}/{path}, where the path may span several segments
 * ("guides/webhooks").
 *
 * laravel/mcp's UriTemplate matches each variable as one segment only, which
 * would make nested pages unreachable, so the last variable is matched here as
 * the rest of the URI.
 */
final class DocsUriTemplate extends UriTemplate
{
    public const TEMPLATE = 'docs://{set}/{version}/{path}';

    public function __construct()
    {
        parent::__construct(self::TEMPLATE);
    }

    /** @return array<string, string>|null */
    public function match(string $uri): ?array
    {
        if (! preg_match('#^docs://([^/?\#]+)/([^/?\#]+)/([^?\#]+)$#', $uri, $matches)) {
            return null;
        }

        return [
            'set' => rawurldecode($matches[1]),
            'version' => rawurldecode($matches[2]),
            'path' => rawurldecode($matches[3]),
        ];
    }
}
