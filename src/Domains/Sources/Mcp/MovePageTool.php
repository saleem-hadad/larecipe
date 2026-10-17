<?php

namespace LaRecipe\Domains\Sources\Mcp;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use LaRecipe\Domains\Sources\Actions\MovePageAction;
use LaRecipe\Domains\Sources\DTOs\DocsMigrationOperation;
use LaRecipe\Domains\Sources\DTOs\PageMove;
use LaRecipe\Domains\Sources\Services\PageAddresses;

#[Name('move_page')]
#[Title('Move a docs page')]
#[Description('Move one docs page to another path (or version, or locale) in the same set. Refused when the new path is taken. Update links and the navigation afterwards (update_navigation with a rename patch). Pass the content_hash read_page returned as based_on.')]
#[IsDestructive]
final class MovePageTool extends AuthorTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('The current page path, for example "billing/exports".')->required(),
            'to' => $schema->string()->description('The new page path, for example "billing/csv-exports".')->required(),
            ...$this->scopeArguments($schema),
            'to_version' => $schema->string()->description('Move into another version. Defaults to the same version.'),
            'to_locale' => $schema->string()->description('Move into another locale. Defaults to the same locale.'),
            ...$this->changeArguments($schema),
            ...$this->setArgument($schema),
        ];
    }

    public function handle(Request $request, AuthorSession $session, MovePageAction $move, PageAddresses $addresses): ResponseFactory
    {
        $data = $request->validate([
            'from' => ['required', 'string', 'max:512'],
            'to' => ['required', 'string', 'max:512'],
            'version' => ['nullable', 'string', 'max:64'],
            'locale' => ['nullable', 'string', 'max:16'],
            'to_version' => ['nullable', 'string', 'max:64'],
            'to_locale' => ['nullable', 'string', 'max:16'],
            'summary' => ['nullable', 'string', 'max:500'],
            'based_on' => ['nullable', 'string', 'max:80'],
            'set' => ['nullable', 'string', 'max:64'],
        ]);

        $set = $session->set($request);

        if (($denial = $session->denial($set)) !== null) {
            return $this->refuse($denial);
        }

        return $this->guarded(function () use ($data, $set, $session, $move, $addresses, $request) {
            $from = $this->ref($set, $session, $data['from'], $data['version'] ?? null, $data['locale'] ?? null);
            $to = $this->ref($set, $session, $data['to'], $data['to_version'] ?? $from->version, $data['to_locale'] ?? $from->locale);
            $author = $session->author($request);

            $result = $move->execute(new PageMove($from, $to, $author, $session->channel(), $data['summary'] ?? null, $data['based_on'] ?? null));

            $manifest = $session->manifest($set);
            $operation = DocsMigrationOperation::movePage($addresses->format($from, $manifest), $addresses->format($to, $manifest), $result->revision?->basedOnHash);

            return $this->respond($result, $session->recordMigration($set, $operation, $result, $author, $data['summary'] ?? null));
        });
    }
}
