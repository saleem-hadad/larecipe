<?php

namespace LaRecipe\Domains\Agents\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Mcp\McpScope;
use LaRecipe\Domains\Agents\Services\PathResolver;

/**
 * Every docs page as an MCP resource: docs://{set}/{version}/{path}, with the
 * page's agent Markdown as its content. An unversioned set uses "current" as
 * its version; another language goes in front of the path ("ar/index").
 */
#[Name('docs-page')]
#[Title('Docs page')]
#[Description('A docs page as Markdown. URI: docs://{set}/{version}/{path}, for example docs://docs/current/guides/webhooks. Unversioned sets use "current" as the version.')]
#[MimeType('text/markdown')]
final class DocPageResource extends Resource implements HasUriTemplate
{
    public function __construct(
        private readonly AgentDocs $docs,
        private readonly McpScope $scope,
        private readonly PathResolver $paths,
    ) {}

    public function uriTemplate(): UriTemplate
    {
        return new DocsUriTemplate;
    }

    public function handle(Request $request): Response
    {
        $key = (string) $request->get('set');
        $set = $this->scope->boundSet() === null || $this->scope->boundSet() === $key ? $this->docs->set($key) : null;

        if ($set === null) {
            return Response::error("There is no doc set named \"{$key}\" here.");
        }

        $version = (string) $request->get('version');
        $resolved = $this->paths->resolve($set, (string) $request->get('path'), $set->isVersioned() ? $version : $set->defaultVersion()->id, null, $set->defaultVersion()->id, $set->defaultLocale()->code);
        $page = $set->version($resolved['ref']->version) === null ? null : $this->docs->page($set, $resolved['ref']);

        if ($page === null) {
            return Response::error("There is no page at \"{$resolved['ref']->path}\" in {$set->manifest->name} {$version}.");
        }

        return Response::text($this->docs->markdown($set, $page));
    }
}
