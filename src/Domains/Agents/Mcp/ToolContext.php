<?php

namespace LaRecipe\Domains\Agents\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use LaRecipe\Domains\Agents\Contracts\AgentDocs;
use LaRecipe\Domains\Agents\Services\PathResolver;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Events\McpToolCalled;
use LaRecipe\Support\Contracts\Dispatcher;

/**
 * What every docs tool does before and after its own work: find the set,
 * version and locale a call is about (honoring the endpoint's binding), resolve
 * the page an agent means, and report the call to the host's listeners.
 *
 * Problems come back as plain sentences the agent can act on, never exceptions.
 */
final class ToolContext
{
    public function __construct(
        private readonly McpScope $scope,
        private readonly AgentDocs $docs,
        private readonly PathResolver $paths,
        private readonly Dispatcher $events,
    ) {}

    public function scope(): McpScope
    {
        return $this->scope;
    }

    /**
     * The optional "set" argument, offered only on the local server.
     *
     * @return array<string, mixed>
     */
    public function setArgument(JsonSchema $schema): array
    {
        if (! $this->scope->isLocal()) {
            return [];
        }

        $keys = array_map(fn (DocSet $set) => $set->key, $this->docs->sets());

        return ['set' => $schema->string()->description('The doc set to use: '.implode(', ', $keys).'. Defaults to '.$this->docs->defaultSet()->key.'.')];
    }

    /** The set a call works on, or a sentence explaining why there is none. */
    public function set(Request $request): DocSet|string
    {
        $key = $this->scope->set(is_string($request->get('set')) ? $request->get('set') : null) ?? $this->docs->defaultSet()->key;
        $set = $this->docs->set($key);

        if ($set !== null) {
            return $set;
        }

        $known = implode(', ', array_map(fn (DocSet $s) => $s->key, $this->docs->sets()));

        return $this->scope->isLocal() && $known !== ''
            ? "There is no doc set named \"{$key}\". Sets: {$known}."
            : 'These docs are not available.';
    }

    /**
     * The version and locale a call reads, defaulting to the scope's (Ask AI)
     * and then the set's defaults.
     *
     * @return array{version: string, locale: string}|string
     */
    public function scopeOf(DocSet $set, mixed $version, mixed $locale): array|string
    {
        $version = is_string($version) && $version !== '' && $set->isVersioned() ? $version : ($this->scope->defaultVersion() ?? $set->defaultVersion()->id);
        $locale = is_string($locale) && $locale !== '' ? $locale : ($this->scope->defaultLocale() ?? $set->defaultLocale()->code);

        if ($set->version($version) === null || ! $this->docs->canViewVersion($set, $version)) {
            return "There is no version \"{$version}\". Versions: ".implode(', ', $this->versions($set)).'.';
        }

        if ($set->locale($locale) === null) {
            return "There is no locale \"{$locale}\". Locales: ".implode(', ', array_map(fn ($l) => $l->code, $set->locales())).'.';
        }

        return ['version' => $version, 'locale' => $locale];
    }

    /** @return array{ref: PageRef, anchor: ?string} */
    public function page(DocSet $set, string $path, mixed $version, mixed $locale, array $scope): array
    {
        return $this->paths->resolve(
            $set,
            $path,
            is_string($version) && $version !== '' ? $version : null,
            is_string($locale) && $locale !== '' ? $locale : null,
            $scope['version'],
            $scope['locale'],
        );
    }

    /** @return list<string> */
    public function versions(DocSet $set): array
    {
        return array_values(array_map(
            fn ($version) => $version->id,
            array_filter($set->versions(), fn ($version) => $this->docs->canViewVersion($set, $version->id)),
        ));
    }

    /** Tell the host's listeners about an MCP call. Ask AI's own calls are reported as AskAiAnswered instead. */
    public function called(DocSet|string|null $set, string $tool, Request $request, ?PageRef $page = null, bool $successful = true): void
    {
        if ($this->scope->isAssistant()) {
            return;
        }

        $arguments = [];

        foreach ($request->all() as $name => $value) {
            if (is_scalar($value) || $value === null) {
                $arguments[$name] = is_string($value) && mb_strlen($value) > 200 ? mb_substr($value, 0, 200).'...' : $value;
            }
        }

        $key = $set instanceof DocSet ? $set->key : ($this->scope->set(is_string($request->get('set')) ? $request->get('set') : null) ?? $this->docs->defaultSet()->key);

        $this->events->dispatch(new McpToolCalled($key, $tool, $this->scope->transport(), $page, $arguments, $successful));
    }
}
