<?php

namespace LaRecipe\Domains\Content\DTOs;

/**
 * The identity of one page: which set, which version, which language, which path.
 *
 * Paths are lowercase, slash-separated and have no extension. A folder's index
 * page is the folder's path ("guides" for guides/index.md); the root index is "index".
 */
final readonly class PageRef
{
    public function __construct(
        public string $set,
        public string $version,
        public string $locale,
        public string $path,
    ) {}

    public static function normalizePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('/\.md$/i', '', $path) ?? $path;
        $path = preg_replace('#(^|/)index$#', '', $path) ?? $path;
        $path = trim($path, '/');

        return $path === '' ? 'index' : $path;
    }

    public function withPath(string $path): self
    {
        return new self($this->set, $this->version, $this->locale, self::normalizePath($path));
    }

    public function withLocale(string $locale): self
    {
        return new self($this->set, $this->version, $locale, $this->path);
    }

    public function withVersion(string $version): self
    {
        return new self($this->set, $version, $this->locale, $this->path);
    }

    public function isIndex(): bool
    {
        return $this->path === 'index';
    }

    public function key(): string
    {
        return "{$this->set}/{$this->version}/{$this->locale}/{$this->path}";
    }

    /** @return array{set: string, version: string, locale: string, path: string} */
    public function toArray(): array
    {
        return ['set' => $this->set, 'version' => $this->version, 'locale' => $this->locale, 'path' => $this->path];
    }
}
