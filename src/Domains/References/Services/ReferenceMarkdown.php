<?php

namespace LaRecipe\Domains\References\Services;

use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;

/**
 * Agent Markdown for a live reference: the current values, in plain portable
 * Markdown. A route becomes a small table, a command a fenced usage block, a
 * model a columns table, an event its payload and listeners.
 */
final class ReferenceMarkdown
{
    public function render(Reference $reference): string
    {
        $blocks = ['**'.ReferenceLabels::kind($reference->kind).'** '.$this->code($reference->title)
            .($reference->description !== null && $reference->description !== '' ? ': '.$this->inline($reference->description) : '')];

        if ($reference->facts !== []) {
            $blocks[] = $this->grid(
                array_map(fn (array $fact) => $fact[0], $reference->facts),
                [array_map(fn (array $fact) => $this->code($fact[1]), $reference->facts)],
            );
        }

        if ($reference->usage !== null) {
            $blocks[] = "```bash\n".$reference->usage."\n```";
        }

        foreach ($reference->tables as $table) {
            $blocks[] = $this->table($table);
        }

        return implode("\n\n", $blocks);
    }

    public function missing(string $kind, string $target): string
    {
        return "> [!WARNING]\n> ".ReferenceLabels::missing($kind, $target);
    }

    private function table(ReferenceTable $table): string
    {
        $rows = array_map(
            fn (array $row) => array_map(fn (string $cell, int $index) => $index === 0 && $cell !== '' ? $this->code($cell) : $this->inline($cell), $row, array_keys($row)),
            $table->rows,
        );

        return $table->title.":\n\n".$this->grid($table->columns, $rows);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows  already formatted cells
     */
    private function grid(array $columns, array $rows): string
    {
        $lines = [
            '| '.implode(' | ', array_map(fn ($column) => $this->inline($column), $columns)).' |',
            '|'.str_repeat(' --- |', count($columns)),
        ];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', $row).' |';
        }

        return implode("\n", $lines);
    }

    private function code(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $fence = str_contains($value, '`') ? '``' : '`';

        return $fence.str_replace(['|', "\n"], ['\\|', ' '], $value).$fence;
    }

    private function inline(string $value): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $value);
    }
}
