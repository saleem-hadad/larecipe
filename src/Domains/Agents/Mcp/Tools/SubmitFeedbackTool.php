<?php

namespace LaRecipe\Domains\Agents\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Mcp\ToolContext;
use LaRecipe\Domains\Delivery\Events\FeedbackSubmitted;
use LaRecipe\Support\Contracts\Dispatcher;

/**
 * submit_feedback: the page widget's agent twin. It dispatches the same
 * FeedbackSubmitted event, tagged as agent feedback, so an agent can report a
 * page that misled it. LaRecipe stores nothing.
 */
#[Name('submit_feedback')]
#[Title('Send feedback on a page')]
#[Description('Tell the docs owners whether a page helped. Use it when a page was wrong, outdated or missing something you needed, and say what in the comment.')]
#[IsReadOnly(false)]
#[IsDestructive(false)]
#[IsIdempotent(false)]
#[IsOpenWorld(false)]
final class SubmitFeedbackTool extends Tool
{
    public function __construct(
        private readonly ToolContext $context,
        private readonly AgentDocs $docs,
        private readonly Dispatcher $events,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('The page path or URL the feedback is about.')->required(),
            'helpful' => $schema->boolean()->description('Whether the page helped.')->required(),
            'comment' => $schema->string()->max(2000)->description('What was wrong or missing, in a sentence or two.'),
            'version' => $schema->string()->description('The docs version. Defaults to the current version.'),
            'locale' => $schema->string()->description('The language, as a locale code. Defaults to the main language.'),
            ...$this->context->setArgument($schema),
        ];
    }

    public function handle(Request $request): Response
    {
        $set = $this->context->set($request);
        $path = trim((string) $request->get('path', ''));
        $helpful = $request->get('helpful');

        if (is_string($set) || $path === '' || ! is_bool($helpful)) {
            $this->context->called(is_string($set) ? null : $set, 'submit_feedback', $request, successful: false);

            return Response::error(is_string($set) ? $set : 'Pass the page path and helpful (true or false).');
        }

        $scope = $this->context->scopeOf($set, $request->get('version'), $request->get('locale'));

        if (is_string($scope)) {
            return Response::error($scope);
        }

        ['ref' => $ref] = $this->context->page($set, $path, $request->get('version'), $request->get('locale'), $scope);
        $page = $this->docs->page($set, $ref);

        if ($page === null) {
            $this->context->called($set, 'submit_feedback', $request, $ref, false);

            return Response::error("There is no page at \"{$ref->path}\". Find the page with search_docs first.");
        }

        $comment = trim(mb_substr((string) $request->get('comment', ''), 0, 2000));

        $this->events->dispatch(new FeedbackSubmitted($set->key, $ref, $helpful, $comment === '' ? null : $comment, 'agent', 'agent'));
        $this->context->called($set, 'submit_feedback', $request, $ref);

        return Response::text("Thanks. Your feedback on \"{$page->title}\" was sent to the docs owners.");
    }
}
