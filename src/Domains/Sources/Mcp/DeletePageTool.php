<?php

namespace LaRecipe\Domains\Sources\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use LaRecipe\Domains\Sources\Actions\DeletePageAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\PageDeletion;
use LaRecipe\Domains\Sources\Services\PageAddresses;

#[Name('delete_page')]
#[Title('Delete a docs page')]
#[Description('Delete one docs page. Remove it from the navigation too (update_navigation with a remove patch). On a database set the page stays in the revision history and can be restored. Pass the content_hash read_page returned as based_on.')]
#[IsDestructive]
final class DeletePageTool extends AuthorTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('The page path, for example "billing/legacy".')->required(),
            ...$this->scopeArguments($schema),
            ...$this->changeArguments($schema),
            ...$this->setArgument($schema),
        ];
    }

    public function handle(Request $request, AuthorSession $session, DeletePageAction $delete, PageAddresses $addresses): ResponseFactory
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:512'],
            'version' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:16'],
            'summary' => ['nullable', 'string', 'max:500'],
            'based_on' => ['nullable', 'string', 'max:80'],
            'set' => ['nullable', 'string', 'max:64'],
        ]);

        $set = $session->set($request);

        if (($denial = $session->denial($set)) !== null) {
            return $this->refuse($denial);
        }

        return $this->guarded(function () use ($data, $set, $session, $delete, $addresses, $request) {
            $ref = $this->ref($set, $session, $data['path'], $data['version'] ?? null, $data['locale'] ?? null);
            $author = $session->author($request);

            $result = $delete->execute(new PageDeletion($ref, $author, $session->channel(), $data['summary'] ?? null, $data['based_on'] ?? null));
            $operation = DocsMigrationOperation::deletePage($addresses->format($ref, $session->manifest($set)), $result->revision?->basedOnHash);

            return $this->respond($result, $session->recordMigration($set, $operation, $result, $author, $data['summary'] ?? null));
        });
    }
}
