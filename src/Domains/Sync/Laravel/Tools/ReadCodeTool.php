<?php

namespace LaRecipe\Domains\Sync\Laravel\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use LaRecipe\Domains\Sync\Services\CodeReader;

/**
 * read_code: the docs agent reads the project's code, read-only, through the
 * CodeAccessPolicy (never .env, dotfiles, config/, storage/, vendor/, keys or
 * anything outside the project).
 */
final class ReadCodeTool implements Tool
{
    public function __construct(private readonly CodeReader $reader) {}

    public function name(): string
    {
        return 'read_code';
    }

    public function description(): string
    {
        return 'Read a file of this project (code, tests, routes, docs files), or list a folder. Paths are relative to the project root, for example "app/Billing/Invoice.php" or "app/Billing". Long files come in parts: pass from and to for line ranges. Configuration, environment, secret and vendor files are never readable.';
    }

    public function handle(Request $request): string
    {
        $from = $request->integer('from');
        $to = $request->integer('to');

        return $this->reader->read($request->string('path')->toString(), $from > 0 ? $from : null, $to > 0 ? $to : null);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('A file or folder, relative to the project root. "" or "." lists the root.')->required(),
            'from' => $schema->integer()->min(1)->description('The first line to read. Defaults to 1.'),
            'to' => $schema->integer()->min(1)->description('The last line to read. Defaults to 400 lines after from.'),
        ];
    }
}
