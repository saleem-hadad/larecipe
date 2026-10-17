<?php

namespace LaRecipe\Domains\Sync\DTOs;

use DateTimeImmutable;

/**
 * One commit, as far as drift detection cares: which, and when.
 */
final readonly class Commit
{
    public function __construct(
        public string $sha,
        public DateTimeImmutable $date,
        public string $subject = '',
    ) {}

    public function short(): string
    {
        return substr($this->sha, 0, 7);
    }

    /** @return array{sha: string, date: string, subject: string} */
    public function toArray(): array
    {
        return ['sha' => $this->sha, 'date' => $this->date->format(DATE_ATOM), 'subject' => $this->subject];
    }
}
