<?php

namespace LaRecipe\Domains\Markdown\DTOs;

final readonly class TocItem
{
    public function __construct(
        public int $level,
        public string $id,
        public string $text,
    ) {}

    public function toArray(): array
    {
        return ['level' => $this->level, 'id' => $this->id, 'text' => $this->text];
    }
}
