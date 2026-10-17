<?php

namespace LaRecipe\Domains\Sync\Laravel\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use LaRecipe\Domains\Sync\Services\DiffReader;

/**
 * git_diff: the change the docs agent documents, read-only. Defaults to the
 * run's own range (the branch against the default branch, plus uncommitted
 * work); files the CodeAccessPolicy denies are named, never shown.
 */
final class GitDiffTool implements Tool
{
    public function __construct(
        private readonly DiffReader $diffs,
        private readonly ?string $base,
        private readonly string $head = 'HEAD',
    ) {}

    public function name(): string
    {
        return 'git_diff';
    }

    public function description(): string
    {
        $range = $this->base === null ? 'the uncommitted changes' : "{$this->base}...{$this->head} plus uncommitted changes";

        return "Read the code change this run documents, as a unified diff. By default: {$range}. Pass path to read one file or folder. Configuration and secret files are listed by name only.";
    }

    public function handle(Request $request): string
    {
        $base = $request->filled('base') ? $request->string('base')->toString() : $this->base;
        $head = $request->filled('head') ? $request->string('head')->toString() : $this->head;
        $uncommitted = $request->has('uncommitted') ? $request->boolean('uncommitted') : true;

        foreach ([$base, $head] as $ref) {
            if ($ref !== null && ! preg_match('#^[A-Za-z0-9._/@^~-]+$#', $ref)) {
                return "[{$ref}] is not a branch or commit name.";
            }
        }

        return $this->diffs->read($base, $head, $uncommitted, $request->filled('path') ? $request->string('path')->toString() : null);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string()->description('Only this file or folder, relative to the project root.'),
            'base' => $schema->string()->description('Compare from this branch or commit. Defaults to the run\'s base.'),
            'head' => $schema->string()->description('Compare up to this branch or commit. Defaults to HEAD.'),
            'uncommitted' => $schema->boolean()->description('Include uncommitted changes. Defaults to true.'),
        ];
    }
}
