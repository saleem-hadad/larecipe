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
use LaRecipe\Domains\Delivery\Events\SearchPerformed;
use LaRecipe\Domains\Search\Contracts\SearchEngine;
use LaRecipe\Domains\Search\DTOs\SearchHit;
use LaRecipe\Domains\Search\DTOs\SearchQuery;
use LaRecipe\Support\Contracts\Dispatcher;

/**
 * search_docs: the server ranker over the caller's visible pages.
 *
 * The same class serves the MCP server and Ask AI (laravel/ai wraps MCP tools),
 * so agents and the reader's assistant retrieve exactly the same results.
 */
#[Name('search_docs')]
#[Title('Search the docs')]
#[Description('Search the documentation and get the best matching pages, with the section and a snippet for each. Search before you read or write a page. Pass a result\'s path to read_page to read it.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class SearchDocsTool extends Tool
{
    public function __construct(
        private readonly ToolContext $context,
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly SearchEngine $engine,
        private readonly Dispatcher $events,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('What to look for: a few words, an API or class name, a command, an error message.')->required(),
            'version' => $schema->string()->description('The docs version to search. Defaults to the current version.'),
            'locale' => $schema->string()->description('The language to search, as a locale code. Defaults to the main language.'),
            'limit' => $schema->integer()->min(1)->max(20)->description('How many results to return (1 to 20, default 8).'),
            ...$this->context->setArgument($schema),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'set' => $schema->string()->required(),
            'version' => $schema->string()->required(),
            'query' => $schema->string()->required(),
            'results' => $schema->array()->items($schema->object([
                'title' => $schema->string()->required(),
                'path' => $schema->string()->required(),
                'url' => $schema->string()->required(),
                'markdown_url' => $schema->string()->required(),
                'heading' => $schema->string()->nullable(),
                'snippet' => $schema->string()->nullable(),
                'description' => $schema->string()->nullable(),
                'score' => $schema->number(),
            ]))->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $query = trim(mb_substr((string) $request->get('query', ''), 0, 500));
        $set = $this->context->set($request);

        if (! is_string($set) && $query === '') {
            $set = 'Pass a query: the words to look for.';
        }

        if (is_string($set)) {
            $this->context->called(null, 'search_docs', $request, successful: false);

            return Response::error($set);
        }

        $scope = $this->context->scopeOf($set, $request->get('version'), $request->get('locale'));

        if (is_string($scope)) {
            $this->context->called($set, 'search_docs', $request, successful: false);

            return Response::error($scope);
        }

        $limit = max(1, min(20, (int) ($request->get('limit') ?: 8)));
        $hits = $this->engine->search(new SearchQuery(
            $set->key,
            $scope['version'],
            $scope['locale'],
            $query,
            $this->docs->pages($set, $scope['version'], $scope['locale']),
            $limit,
        ));

        $results = array_map(fn (SearchHit $hit) => [
            'title' => $hit->title,
            'path' => $hit->ref->path,
            'url' => $this->links->page($set, $hit->ref).($hit->anchor !== null ? '#'.$hit->anchor : ''),
            'markdown_url' => $this->links->page($set, $hit->ref, true),
            'heading' => $hit->heading,
            'snippet' => $hit->snippet,
            'description' => $hit->description,
            'score' => round($hit->score, 4),
        ], array_slice($hits, 0, $limit));

        $this->events->dispatch(new SearchPerformed(
            $set->key,
            $scope['version'],
            $scope['locale'],
            $query,
            count($results),
            $this->context->scope()->isAssistant() ? 'assistant' : 'mcp',
            'agent',
        ));
        $this->context->called($set, 'search_docs', $request);

        return Response::structured([
            'set' => $set->key,
            'version' => $scope['version'],
            'query' => $query,
            'results' => $results,
        ]);
    }
}
