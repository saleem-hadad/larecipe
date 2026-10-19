<?php

namespace LaRecipe\Domains\Delivery\DTOs;

/**
 * What a docs URL asks for, once the prefix, locale and version are peeled off.
 */
final readonly class DocsRequest
{
    public const HTML = 'html';

    public const MARKDOWN = 'markdown';

    public const JSON = 'json';

    public function __construct(
        public string $set,
        public string $locale,
        public string $version,
        public string $path,
        public string $format = self::HTML,
        public ?string $redirect = null,
        public bool $explicitMarkdown = false,
    ) {}

    public function isMarkdown(): bool
    {
        return $this->format === self::MARKDOWN;
    }

    public function isJson(): bool
    {
        return $this->format === self::JSON;
    }
}
