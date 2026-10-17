<?php

namespace LaRecipe\Domains\Validation\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request as HttpRequest;
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
use LaRecipe\Domains\Content\Services\SetRegistry;
use LaRecipe\Domains\Validation\Actions\ValidateDocsAction;

/**
 * validate_docs: the larecipe:validate --json report, for the agent that just
 * wrote a page. On a set's web endpoint it is bound to that set (and offered
 * only to callers the manageLarecipe gate allows, since it reads every page).
 */
#[Name('validate_docs')]
#[Title('Validate the docs')]
#[Description('Check the docs for broken links and anchors, navigation entries without pages, schema errors in larecipe.json, navigation.json and frontmatter, unknown components, missing snippets and more. Run it after writing, and fix every error. Each issue has a stable rule id and often a fix.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld(false)]
final class ValidateDocsTool extends Tool
{
    public function __construct(
        private readonly ValidateDocsAction $validate,
        private readonly SetRegistry $sets,
        private readonly HttpRequest $http,
    ) {}

    public function schema(JsonSchema $schema): array
    {
        $arguments = ['version' => $schema->string()->description('Only check this version. Defaults to every version.')];

        if ($this->boundSet() === null) {
            $arguments['set'] = $schema->string()->description('The doc set to check: '.implode(', ', array_keys($this->sets->configs())).'. Defaults to '.$this->sets->default()->key.'.');
        }

        return $arguments;
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $key = $this->boundSet() ?? (is_string($request->get('set')) && $request->get('set') !== '' ? $request->get('set') : $this->sets->default()->key);

        if (! $this->sets->has($key)) {
            return Response::error("There is no doc set named \"{$key}\". Sets: ".implode(', ', array_keys($this->sets->configs())).'.');
        }

        $version = is_string($request->get('version')) && $request->get('version') !== '' ? $request->get('version') : null;

        if ($version !== null && $this->sets->get($key)->version($version) === null) {
            return Response::error("The \"{$key}\" set has no version \"{$version}\".");
        }

        return Response::structured($this->validate->execute($key, $version)->toArray());
    }

    /** The set of the web endpoint serving this call, null on the local server. */
    private function boundSet(): ?string
    {
        $set = $this->http->route()?->defaults['larecipeSet'] ?? null;

        return is_string($set) && $set !== '' ? $set : null;
    }
}
