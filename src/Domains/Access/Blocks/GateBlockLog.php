<?php

namespace LaRecipe\Domains\Access\Blocks;

/**
 * Where the gate blocks of one render sat in the page source. FilterGatedSourceAction
 * hands one to the renderer (in the render context under CONTEXT_KEY) to project
 * the reader's view back onto the raw Markdown.
 */
final class GateBlockLog
{
    public const CONTEXT_KEY = 'gate_block_log';

    /** @var list<array{0: int, 1: int}> line ranges (1-based, inclusive) of blocks removed for the reader */
    public array $removed = [];

    /** @var list<int> lines holding the opening and closing tags of blocks the reader keeps */
    public array $tags = [];

    public function remove(?int $start, ?int $end): void
    {
        if ($start !== null && $end !== null) {
            $this->removed[] = [$start, $end];
        }
    }

    public function keep(?int $start, ?int $end, bool $selfClosing): void
    {
        if ($start === null) {
            return;
        }

        $this->tags[] = $start;

        if (! $selfClosing && $end !== null && $end !== $start) {
            $this->tags[] = $end;
        }
    }

    public function isEmpty(): bool
    {
        return $this->removed === [] && $this->tags === [];
    }
}
