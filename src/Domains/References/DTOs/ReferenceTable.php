<?php

namespace LaRecipe\Domains\References\DTOs;

/**
 * One table inside a reference: a model's columns, a command's options, the
 * validation rules of a route's form request.
 */
final readonly class ReferenceTable
{
    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     */
    public function __construct(
        public string $title,
        public array $columns,
        public array $rows,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['title'] ?? ''),
            array_values(array_map('strval', (array) ($data['columns'] ?? []))),
            array_values(array_map(fn ($row) => array_values(array_map('strval', (array) $row)), (array) ($data['rows'] ?? []))),
        );
    }

    public function toArray(): array
    {
        return ['title' => $this->title, 'columns' => $this->columns, 'rows' => $this->rows];
    }
}
