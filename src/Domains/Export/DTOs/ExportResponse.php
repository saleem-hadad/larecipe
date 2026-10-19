<?php

namespace LaRecipe\Domains\Export\DTOs;

/**
 * One answer from the site while exporting it.
 */
final readonly class ExportResponse
{
    public function __construct(
        public int $status,
        public string $contentType,
        public string $body,
        public ?string $location = null,
    ) {}

    public function isOk(): bool
    {
        return $this->status === 200;
    }

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400 && $this->location !== null;
    }

    public function isHtml(): bool
    {
        return str_starts_with(strtolower($this->contentType), 'text/html');
    }
}
