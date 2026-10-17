<?php

namespace LaRecipe\Domains\Sources\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\DTOs\WriteResult;
use LaRecipe\Domains\Sources\Exceptions\InvalidContentChange;
use LaRecipe\Domains\Sources\Exceptions\ReadOnlySource;
use LaRecipe\Domains\Sources\Exceptions\UnsafePath;
use LaRecipe\Domains\Sources\Services\SetLayout;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * What the five author tools share: the common arguments, how a page address
 * is resolved, and one response shape (outcome, revision, validation, and the
 * docs migration file for database sets).
 *
 * The tools only translate MCP calls into the Sources Actions; validation,
 * review modes and events happen there, exactly as for every other writer.
 */
abstract class AuthorTool extends Tool
{
    /** @return array<string, mixed> the `set` argument every author tool accepts */
    protected function setArgument(JsonSchema $schema): array
    {
        return ['set' => $schema->string()->description('The doc set key, when the app has more than one. Defaults to the default set.')];
    }

    /** @return array<string, mixed> */
    protected function scopeArguments(JsonSchema $schema): array
    {
        return [
            'version' => $schema->string()->description('The version, for versioned sets, for example "3.x". Defaults to the default version.'),
            'locale' => $schema->string()->description('The locale code. Defaults to the default locale.'),
        ];
    }

    /** @return array<string, mixed> */
    protected function changeArguments(JsonSchema $schema): array
    {
        return [
            'summary' => $schema->string()->description('One line describing the change, for the revision list and the pull request.'),
            'based_on' => $schema->string()->description('The content hash you read (content_hash from read_page). The change is refused if the content changed since.'),
        ];
    }

    /** @return array<string, mixed> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'outcome' => $schema->string()->enum(['published', 'drafted', 'conflicted', 'unchanged', 'rejected'])->required(),
            'message' => $schema->string()->nullable(),
            'revision' => $schema->object()->nullable(),
            'validation' => $schema->object()->required(),
            'docs_migration' => $schema->string()->nullable()->description('The docs migration file this change was written into (database sets, local server). Commit it with the code.'),
        ];
    }

    protected function ref(SetConfig $set, AuthorSession $session, string $path, ?string $version, ?string $locale): PageRef
    {
        $manifest = $session->manifest($set);

        return new PageRef(
            $set->key,
            $version ?? SetLayout::defaultVersion($manifest),
            $locale ?? SetLayout::defaultLocale($manifest),
            PageRef::normalizePath($path),
        );
    }

    protected function respond(WriteResult $result, ?string $migration = null): ResponseFactory
    {
        $payload = $result->toArray() + ['docs_migration' => $migration];

        if ($result->ok()) {
            return Response::structured($payload);
        }

        return Response::make([Response::error((string) $result->message)])->withStructuredContent($payload);
    }

    protected function refuse(string $message): ResponseFactory
    {
        return $this->respond(WriteResult::rejected(new ValidationReport, $message));
    }

    /**
     * Turns the exceptions a bad request can cause (an unsafe path, a taken
     * address, a read-only set) into an error the agent can read and act on.
     *
     * @param  \Closure(): ResponseFactory  $call
     */
    protected function guarded(\Closure $call): ResponseFactory
    {
        try {
            return $call();
        } catch (UnsafePath|InvalidContentChange|ReadOnlySource $exception) {
            return $this->refuse($exception->getMessage());
        }
    }
}
