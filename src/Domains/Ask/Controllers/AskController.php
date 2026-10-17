<?php

namespace LaRecipe\Domains\Ask\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Ai\Responses\StreamedAgentResponse;
use Laravel\Ai\Vercel\Vercel;
use LaRecipe\Domains\Access\Services\PageAccess;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Contracts\DocsLinks;
use LaRecipe\Domains\Agents\Mcp\McpScope;
use LaRecipe\Domains\Agents\Mcp\Tools\ReadPageTool;
use LaRecipe\Domains\Agents\Mcp\Tools\SearchDocsTool;
use LaRecipe\Domains\Ask\Laravel\CitingVercelProtocol;
use LaRecipe\Domains\Ask\Laravel\DocsAssistant;
use LaRecipe\Domains\Ask\Services\CitationGuard;
use LaRecipe\Domains\Ask\Services\DailyBudget;
use LaRecipe\Domains\Ask\Support\AiFeatures;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Delivery\Events\AskAiAnswered;
use LaRecipe\Domains\Delivery\Support\RequestClassifier;
use LaRecipe\Domains\Delivery\Support\SetFromRequest;
use LaRecipe\Support\Contracts\Dispatcher;
use Symfony\Component\HttpFoundation\Response;

/**
 * POST /{prefix}/{version}/_ask: Ask AI, streamed with the Vercel AI SDK UI
 * message protocol, so the reader UI's useChat reads it directly.
 *
 * In order: the set has Ask AI on, the reader may see this version, the
 * askLarecipe gate allows them, the named rate limiter (on the route) and the
 * set's daily budget have room. The assistant then answers from the docs with
 * the same tools as the MCP server, bound to this reader's view of the set.
 */
final class AskController
{
    public function __construct(
        private readonly SetFromRequest $current,
        private readonly AgentDocs $docs,
        private readonly DocsLinks $links,
        private readonly PageAccess $access,
        private readonly AiFeatures $features,
        private readonly DailyBudget $budget,
        private readonly McpScope $scope,
        private readonly SearchDocsTool $search,
        private readonly ReadPageTool $read,
        private readonly RequestClassifier $classifier,
        private readonly Dispatcher $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $set = $this->docs->set($this->current->key($request));

        if ($set === null || ! $this->features->askEnabled($set)) {
            abort(404);
        }

        [$version, $locale] = $this->scopeOf($set, $request->route('scope')) ?? abort(404);

        if (! $this->docs->canViewVersion($set, $version)) {
            abort(404);
        }

        if (! $this->access->allows(PageAccess::ASK, [$set->key])) {
            abort(403, 'You may not use Ask AI on these docs.');
        }

        $request->validate(['messages' => ['required', 'array', 'min:1', 'max:'.(int) $set->config->setting('ai.ask.max_messages', 20)]]);

        $chat = Vercel::chat($request);
        $question = trim((string) $chat->message()?->content);

        if ($question === '' || mb_strlen($question) > (int) $set->config->setting('ai.ask.max_question_length', 2000)) {
            return new JsonResponse(['message' => 'Ask a question of at most '.(int) $set->config->setting('ai.ask.max_question_length', 2000).' characters.'], 422);
        }

        if (! $this->budget->take($set)) {
            return new JsonResponse(['message' => 'Ask AI has answered all the questions it can today. Search the docs, or try again tomorrow.'], 429);
        }

        $this->scope->bindAssistant($set->key, $version, $locale);

        $guard = $this->guard($set, $version, $locale);
        $kind = $this->classifier->classify($request);

        $assistant = new DocsAssistant(
            $set->manifest->name,
            $set->isVersioned() ? ($set->version($version)?->label ?? $version) : '',
            [$this->search, $this->read],
            max(1, (int) $set->config->setting('ai.ask.max_steps', 6)),
            $this->providers($set->config->setting('ai.ask.provider')),
            $this->option($set->config->setting('ai.ask.model')),
        );

        return $assistant
            ->withMessages($chat->history())
            ->stream($chat)
            ->then(function (StreamedAgentResponse $response) use ($set, $version, $locale, $question, $guard, $kind) {
                $this->events->dispatch(new AskAiAnswered(
                    set: $set->key,
                    version: $version,
                    locale: $locale,
                    question: $question,
                    covered: ! str_starts_with(ltrim($response->text), DocsAssistant::NOT_COVERED),
                    citations: array_column($guard->citations($response->text), 'url'),
                    kind: $kind,
                    inputTokens: $response->usage->inputTokens,
                    outputTokens: $response->usage->outputTokens,
                ));
            })
            ->usingProtocol(new CitingVercelProtocol($chat->messageId(), $guard))
            ->toResponse($request);
    }

    /**
     * The locale and version in front of _ask: "", "2.x", "ar", "ar/2.x".
     *
     * @return array{0: string, 1: string}|null
     */
    private function scopeOf(DocSet $set, ?string $scope): ?array
    {
        $segments = array_values(array_filter(explode('/', (string) $scope), fn (string $s) => $s !== ''));
        $locale = $set->defaultLocale()->code;
        $version = $set->defaultVersion()->id;

        if ($segments !== [] && $set->isMultilingual() && $set->locale($segments[0]) !== null) {
            $locale = array_shift($segments);
        }

        if ($segments !== [] && $set->isVersioned() && $set->version($segments[0]) !== null) {
            $version = array_shift($segments);
        }

        return $segments === [] ? [$version, $locale] : null;
    }

    /** Every page this reader may see in the version (and the main language it falls back to). */
    private function guard(DocSet $set, string $version, string $locale): CitationGuard
    {
        $known = [];

        foreach (array_unique([$set->defaultLocale()->code, $locale]) as $code) {
            foreach ($this->docs->pages($set, $version, $code) as $summary) {
                $url = $this->links->page($set, $summary->ref);
                $known[CitationGuard::normalize($url)] = ['url' => $url, 'title' => $summary->title];
            }
        }

        return new CitationGuard($known);
    }

    private function option(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** One provider, or a failover list, passed as is: laravel/ai tries each in order when one fails. */
    private function providers(mixed $value): string|array|null
    {
        return is_array($value) && $value !== [] ? $value : $this->option($value);
    }
}
