<?php

namespace LaRecipe\Domains\Agents\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\Mcp\ToolContext;
use LaRecipe\Domains\Agents\Services\MarkdownSection;

/**
 * read_page: a page's agent Markdown, the same text served at its .md URL
 * (the No-Cloaking Rule), optionally cut down to one section.
 *
 * The page's metadata travels in the Markdown's own YAML header and in the
 * result's _meta, so the text an agent reads is never duplicated. The one
 * addition is the page's content hash, in _meta and on the last line: the
 * based_on the author tools take, so a write never overwrites a newer edit.
 */
#[Name('read_page')]
#[Title('Read a docs page')]
#[Description('Read one docs page as Markdown. Pass the path from search_docs or list_pages (for example "guides/webhooks"), or the page URL. Pass section (a heading or its #anchor) to read only that part of the page. The result ends with the page\'s content_hash (also in its _meta): pass it as based_on when you write, move or delete the page.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class ReadPageTool extends Tool
{
    public function __construct(
        private readonly ToolContext $context,
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly MarkdownSection $sections,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('The page path ("guides/webhooks"), or its URL or .md URL.')->required(),
            'version' => $schema->string()->description('The docs version. Defaults to the current version.'),
            'locale' => $schema->string()->description('The language, as a locale code. Defaults to the main language.'),
            'section' => $schema->string()->description('Only return this section: a heading text or its anchor id.'),
            ...$this->context->setArgument($schema),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $set = $this->context->set($request);
        $path = trim((string) $request->get('path', ''));

        if (is_string($set) || $path === '') {
            $this->context->called(is_string($set) ? null : $set, 'read_page', $request, successful: false);

            return Response::error(is_string($set) ? $set : 'Pass the path of the page to read.');
        }

        $scope = $this->context->scopeOf($set, $request->get('version'), $request->get('locale'));

        if (is_string($scope)) {
            $this->context->called($set, 'read_page', $request, successful: false);

            return Response::error($scope);
        }

        ['ref' => $ref, 'anchor' => $anchor] = $this->context->page($set, $path, $request->get('version'), $request->get('locale'), $scope);
        $page = $this->docs->page($set, $ref);

        if ($page === null) {
            $this->context->called($set, 'read_page', $request, $ref, false);

            return Response::error("There is no page at \"{$ref->path}\" in {$set->manifest->name}. Find pages with search_docs or list_pages.");
        }

        $markdown = $this->docs->markdown($set, $page);
        $section = trim((string) ($request->get('section') ?: $anchor));

        if ($section !== '') {
            $part = $this->sections->extract($markdown, $section);

            if ($part === null) {
                $this->context->called($set, 'read_page', $request, $ref, false);
                $headings = array_map(fn (array $h) => $h['text'], $this->sections->headings($markdown));

                return Response::error("The page \"{$page->title}\" has no section \"{$section}\". Sections: ".implode('; ', $headings).'.');
            }

            $markdown = $part;
        }

        $this->context->called($set, 'read_page', $request, $ref);

        $meta = [
            'title' => $page->title,
            'path' => $ref->path,
            'version' => $ref->version,
            'locale' => $ref->locale,
            'url' => $this->links->page($set, $ref),
            'markdown_url' => $this->links->page($set, $ref, true),
            'content_hash' => $page->contentHash,
        ];

        // Ask AI never writes, so its answers do not need the hash in the text.
        $text = $this->context->scope()->isAssistant()
            ? $markdown
            : rtrim($markdown)."\n\ncontent_hash: {$page->contentHash} (pass it as based_on when you write, move or delete this page)\n";

        // Metadata in _meta, not structuredContent: laravel/ai (Ask AI, larecipe:sync,
        // larecipe:pull) hands a model the structured content instead of the text.
        return Response::make(Response::text($text))->withMeta($meta);
    }
}
