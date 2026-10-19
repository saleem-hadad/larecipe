<?php

namespace LaRecipe\Domains\Sources\Mcp;

use Illuminate\Contracts\Container\Container;
use Laravel\Mcp\Request;
use LaRecipe\Domains\Access\Contracts\Authorizer;
use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Content\Enums\Actor;
use LaRecipe\Domains\Sources\Contracts\SourceResolver;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Enums\Channel;
use LaRecipe\Domains\Sources\Laravel\DocsMigrationWriter;

/**
 * Everything the author tools need to know about who is calling and from where.
 *
 * On the local stdio server (the owner's coding session) changes ride in the
 * pull request: a repository set's files change in the working tree, and a
 * database set's change is applied to the local database for preview and
 * written into the branch's docs migration. On the web server (remote author
 * tools) changes happen outside any pull request: database sets only, behind
 * the manageLarecipe gate, under the set's review mode.
 *
 * The server-side docs agent (larecipe:sync) runs the same tools in the
 * console and passes its own channel and author, so its changes follow the
 * review mode for where they land and are attributed to the agent.
 */
final class AuthorSession
{
    public function __construct(
        private readonly Container $app,
        private readonly SourceResolver $sources,
        private readonly Authorizer $authorizer,
        private readonly ?bool $local = null,
        private readonly ?Channel $channel = null,
        private readonly ?Provenance $agent = null,
    ) {}

    /**
     * The local stdio server runs in the console and serves no HTTP route.
     * Checking the route too matters under Octane, where web requests are
     * also served from a console process.
     */
    public function isLocal(): bool
    {
        if ($this->local !== null) {
            return $this->local;
        }

        $servingHttp = $this->app->bound('request') && $this->app->make('request')->route() !== null;

        return $this->app->runningInConsole() && ! $servingHttp;
    }

    /** The set the call targets: the endpoint's own set on the web, the `set` argument or the default locally. */
    public function set(Request $request): ?SetConfig
    {
        /** @var array<string, SetConfig> $sets */
        $sets = $this->app->make('larecipe.set-configs');
        $bound = $this->boundSet();
        $asked = $request->get('set');

        if ($bound !== null && $asked !== null && $asked !== $bound) {
            return null;
        }

        $key = (string) ($asked ?? $bound ?? $this->app->make('config')->get('larecipe.default', 'docs'));

        return $sets[$key] ?? null;
    }

    public function manifest(SetConfig $set): ?Manifest
    {
        return $this->sources->for($set->key)->manifest();
    }

    /** Why this call may not write to the set, or null when it may. */
    public function denial(?SetConfig $set): ?string
    {
        if ($set === null && ($bound = $this->boundSet()) !== null) {
            return "This endpoint only serves the [{$bound}] docs. Leave out set, or connect to the other set's own MCP endpoint.";
        }

        if ($set === null) {
            return 'Unknown doc set. Pass a set key from config/larecipe.php (sets.*), or omit it for the default set.';
        }

        if ($this->isLocal()) {
            return null;
        }

        if ($set->driver !== 'database') {
            return "The [{$set->key}] set lives in the repository, so its changes belong in git. Remote author tools only write to database sets.";
        }

        return $this->authorizer->allows('manageLarecipe', [$set->key])
            ? null
            : "You are not allowed to manage the [{$set->key}] docs (the manageLarecipe gate).";
    }

    /** The agent making the change, named after the MCP client when it says who it is. */
    public function author(Request $request): Provenance
    {
        if ($this->agent !== null) {
            return $this->agent;
        }

        $client = $request->meta()['io.modelcontextprotocol/clientInfo']['name'] ?? null;
        $name = is_string($client) && trim($client) !== ''
            ? trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($client)), '-')
            : 'mcp-agent';

        return new Provenance(Actor::Agent, $name);
    }

    public function channel(): Channel
    {
        return $this->channel ?? ($this->isLocal() ? Channel::PullRequest : Channel::RemoteAgent);
    }

    /**
     * After a change to a database set that rides in the pull request: add
     * it to the branch's docs migration, so the change travels with the code.
     *
     * @return string|null the migration file, relative to the app root
     */
    public function recordMigration(SetConfig $set, ?DocsMigrationOperation $operation, WriteResult $result, Provenance $author, ?string $summary): ?string
    {
        if ($this->channel() !== Channel::PullRequest || $set->driver !== 'database' || $operation === null || ! $result->isPublished()) {
            return null;
        }

        $file = $this->app->make(DocsMigrationWriter::class)->record($set->key, $operation, $result, $author, $summary);
        $base = rtrim($this->app->basePath(), '/').'/';

        return $file !== null && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }

    /**
     * The set of the web endpoint serving this call, read from its route the
     * way every other set route finds its set (each /{prefix}/mcp route
     * carries its set key). Null on the local server.
     */
    private function boundSet(): ?string
    {
        $route = $this->app->bound('request') ? $this->app->make('request')->route() : null;
        $key = $route?->defaults['larecipeSet'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }
}
