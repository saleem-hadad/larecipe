<?php

namespace LaRecipe\Domains\Sources\DTOs;

use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Sources\Enums\SubjectType;

/**
 * The thing a revision history belongs to: one page, the navigation of one
 * version and locale, or the set's manifest.
 */
final readonly class Subject
{
    public function __construct(
        public string $set,
        public SubjectType $type,
        public ?PageRef $ref = null,
        public ?string $version = null,
        public ?string $locale = null,
    ) {}

    public static function page(PageRef $ref): self
    {
        return new self($ref->set, SubjectType::Page, $ref, $ref->version, $ref->locale);
    }

    public static function navigation(string $set, string $version, string $locale): self
    {
        return new self($set, SubjectType::Navigation, null, $version, $locale);
    }

    public static function manifest(string $set): self
    {
        return new self($set, SubjectType::Manifest);
    }

    public function key(): string
    {
        return Revision::subjectKey($this->type, $this->ref, $this->version, $this->locale);
    }
}
