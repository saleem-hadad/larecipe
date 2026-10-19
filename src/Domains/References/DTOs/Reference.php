<?php

namespace LaRecipe\Domains\References\DTOs;

/**
 * What a live reference resolved to: a small, renderer-neutral description of
 * one route, command, model or event, read from the app when the page rendered.
 *
 * Both renderers (server HTML and agent Markdown) and the snapshot file work
 * from this one shape, so a page reads the same in every representation.
 */
final readonly class Reference
{
    /**
     * @param  string  $kind  route, artisan, model or event
     * @param  string  $target  what the author wrote (a route name, a command, a class)
     * @param  string  $title  the headline: "POST /orders", "orders:prune", "App\Models\Order"
     * @param  list<array{0: string, 1: string}>  $facts  short label and value pairs, in order
     * @param  ?string  $usage  a usage line, for commands
     * @param  list<ReferenceTable>  $tables
     */
    public function __construct(
        public string $kind,
        public string $target,
        public string $title,
        public ?string $description = null,
        public array $facts = [],
        public ?string $usage = null,
        public array $tables = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            kind: (string) ($data['kind'] ?? ''),
            target: (string) ($data['target'] ?? ''),
            title: (string) ($data['title'] ?? ''),
            description: isset($data['description']) ? (string) $data['description'] : null,
            facts: array_values(array_map(fn ($fact) => [(string) ($fact[0] ?? ''), (string) ($fact[1] ?? '')], (array) ($data['facts'] ?? []))),
            usage: isset($data['usage']) ? (string) $data['usage'] : null,
            tables: array_values(array_map(fn ($table) => ReferenceTable::fromArray((array) $table), (array) ($data['tables'] ?? []))),
        );
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'target' => $this->target,
            'title' => $this->title,
            'description' => $this->description,
            'facts' => $this->facts,
            'usage' => $this->usage,
            'tables' => array_map(fn (ReferenceTable $table) => $table->toArray(), $this->tables),
        ];
    }
}
