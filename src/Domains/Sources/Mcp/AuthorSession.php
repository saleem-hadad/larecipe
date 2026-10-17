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
 */
final class AuthorSession
{
    /** The bound set of a per-set web MCP endpoint, when the server binds one. */
    public const BOUND_SET = 'larecipe.mcp.set';

    public function __construct(
        private readonly Container $app,
        private readonly SourceResolver $sources,
        private readonly Authorizer $authorizer,
        private readonly bool $local,
    ) {}

    public function isLocal(): bool
    {
        return $this->local;
    }

    /** The set the call targets: the endpoint's own set on the web, the `set` argument or the default locally. */
    public function set(Request $request): ?SetConfig
    {
        /** @var array<string, SetConfig> $sets */
        $sets = $this->app->make('larecipe.set-configs');
        $bound = $this->app->bound(self::BOUND_SET) ? (string) $this->app->make(self::BOUND_SET) : null;
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
        if ($set === null) {
            return 'Unknown doc set. Pass a set key from config/larecipe.php (sets.*), or omit it for the default set.';
        }

        if ($this->local) {
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
        $client = $request->meta()['io.modelcontextprotocol/clientInfo']['name'] ?? null;
        $name = is_string($client) && trim($client) !== ''
            ? trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($client)), '-')
            : 'mcp-agent';

        return new Provenance(Actor::Agent, $name);
    }

    public function channel(): Channel
    {
        return $this->local ? Channel::PullRequest : Channel::RemoteAgent;
    }

    /**
     * After a local change to a database set: add it to the branch's docs
     * migration, so the change travels in the pull request.
     *
     * @return string|null the migration file, relative to the app root
     */
    public function recordMigration(SetConfig $set, ?DocsMigrationOperation $operation, WriteResult $result, Provenance $author, ?string $summary): ?string
    {
        if (! $this->local || $set->driver !== 'database' || $operation === null || ! $result->isPublished()) {
            return null;
        }

        $file = $this->app->make(DocsMigrationWriter::class)->record($set->key, $operation, $result, $author, $summary);
        $base = rtrim($this->app->basePath(), '/').'/';

        return $file !== null && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
