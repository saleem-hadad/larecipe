<?php

namespace LaRecipe\Domains\Export\Services;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Writes the export into its output folder and remembers what it wrote. Every
 * path is checked to stay inside the folder, whatever a URL says.
 */
final class ExportFiles
{
    /** @var array<string, true> */
    private array $written = [];

    public function __construct(private readonly string $out) {}

    /** The file a URL path is served from: pages as folder/index.html, everything else as named. */
    public static function fileFor(string $path, bool $page): string
    {
        $path = trim((string) parse_url($path, PHP_URL_PATH), '/');

        if (! $page) {
            return $path;
        }

        return $path === '' ? 'index.html' : $path.'/index.html';
    }

    public function write(string $relative, string $contents): void
    {
        $target = $this->target($relative);

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0755, true);
        }

        file_put_contents($target, $contents);
        $this->written[$this->normalize($relative)] = true;
    }

    public function has(string $relative): bool
    {
        return isset($this->written[$this->normalize($relative)]);
    }

    /** Copies a folder as it is (the built UI) under a relative prefix. */
    public function copyFolder(string $folder, string $prefix): void
    {
        if (! is_dir($folder)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) {
                $relative = trim($prefix, '/').'/'.ltrim(substr($file->getPathname(), strlen($folder)), '/');
                $this->write($relative, (string) file_get_contents($file->getPathname()));
            }
        }
    }

    /** @return list<string> */
    public function written(): array
    {
        $files = array_keys($this->written);
        sort($files);

        return $files;
    }

    private function target(string $relative): string
    {
        $relative = $this->normalize($relative);

        if ($relative === '' || in_array('..', explode('/', $relative), true)) {
            throw new InvalidArgumentException("Refusing to write outside the export folder: {$relative}");
        }

        return rtrim($this->out, '/').'/'.$relative;
    }

    private function normalize(string $relative): string
    {
        return trim(str_replace('\\', '/', $relative), '/');
    }
}
