<?php

namespace LaRecipe\Domains\Export\Contracts;

use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Export\DTOs\ExportResponse;

/**
 * How the exporter reads the site: as a guest, exactly as the server would
 * answer. The Laravel adapter serves each request in process through the HTTP
 * kernel; the future standalone CLI brings its own.
 */
interface SiteClient
{
    /** A GET for a domain-relative path ("/docs/3.x/install"), as a signed-out reader. */
    public function get(string $path, string $accept = 'text/html'): ExportResponse;

    /** The domain-relative path of a page, or of its Markdown twin. */
    public function pagePath(DocSet $set, PageRef $ref, bool $markdown = false): string;

    /** The origin absolute URLs in responses start with ("https://docs.acme.dev"). */
    public function origin(): string;

    /** @return array<string, string> folders to copy as they are: URL path prefix => local folder (the built UI) */
    public function staticFolders(): array;

    /**
     * The contents of a file the web server serves next to the app (the host's
     * public/ folder) at this domain-relative path, or null. Only a page's own
     * references are asked for; scripts and dot files are never returned.
     */
    public function publicFile(string $path): ?string;
}
