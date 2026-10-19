<?php

namespace LaRecipe\Domains\Upgrade\DTOs;

/**
 * A LaRecipe 2 app as the upgrade sees it: where it lives, its v2 config (already
 * evaluated, so env() calls in it are resolved) and how the owner wants it moved.
 */
final readonly class V2Project
{
    /**
     * @param  array<string, mixed>  $config  the v2 config/larecipe.php array
     * @param  ?string  $moveTo  a folder relative to the base path to move the docs into ("docs"), or null to keep them
     * @param  bool  $relativeLinks  also rewrite /docs/{{version}}/page links to relative .md links
     */
    public function __construct(
        public string $basePath,
        public array $config,
        public string $appName = 'Docs',
        public ?string $moveTo = null,
        public bool $relativeLinks = false,
    ) {}

    public function path(string $relative = ''): string
    {
        return rtrim($this->basePath, '/').($relative === '' ? '' : '/'.ltrim($relative, '/'));
    }

    /** The v2 docs folder, relative to the base path ("resources/docs"). */
    public function docsPath(): string
    {
        return trim((string) ($this->config['docs']['path'] ?? '/resources/docs'), '/');
    }

    /** Where the docs live after the upgrade, relative to the base path. */
    public function targetDocsPath(): string
    {
        return $this->moveTo !== null && trim($this->moveTo, '/') !== '' ? trim($this->moveTo, '/') : $this->docsPath();
    }

    /** The v2 route prefix without slashes ("docs"). */
    public function routePrefix(): string
    {
        return trim((string) ($this->config['docs']['route'] ?? '/docs'), '/');
    }

    /** @return list<string> */
    public function versions(): array
    {
        return array_values(array_map('strval', (array) ($this->config['versions']['published'] ?? [])));
    }

    public function defaultVersion(): ?string
    {
        $default = $this->config['versions']['default'] ?? null;

        return is_scalar($default) ? (string) $default : ($this->versions()[0] ?? null);
    }
}
