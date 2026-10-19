<?php

namespace LaRecipe\Domains\Export\DTOs;

use LaRecipe\Domains\Content\DTOs\DocSet;

/**
 * What to export and where: the sets, the output folder, and the public base
 * URL the files will be served from.
 */
final readonly class ExportOptions
{
    /**
     * @param  list<DocSet>  $sets
     * @param  ?string  $baseUrl  where the export will live ("https://docs.acme.dev" or "https://acme.github.io/docs"), or null for the app URL
     */
    public function __construct(
        public array $sets,
        public string $out,
        public ?string $baseUrl = null,
    ) {}

    /** The path part of the base URL ("/docs" for https://acme.github.io/docs), or "" at a domain root. */
    public function basePath(): string
    {
        return $this->baseUrl === null ? '' : rtrim((string) parse_url($this->baseUrl, PHP_URL_PATH), '/');
    }
}
