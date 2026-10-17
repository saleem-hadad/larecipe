<?php

namespace LaRecipe\Domains\Sources\DTOs;

/**
 * A doc set as canonical files in a directory: larecipe.json, each version's
 * navigation.json, Markdown pages and assets, laid out like a repository set.
 * Every source move goes through one, and exports and imports are one.
 */
final readonly class Bundle
{
    public function __construct(
        public string $path,
        public bool $temporary = false,
    ) {}

    public static function temporary(): self
    {
        $path = sys_get_temp_dir().'/larecipe-bundle-'.bin2hex(random_bytes(8));
        mkdir($path, 0755, true);

        return new self($path, true);
    }

    public static function isArchive(string $path): bool
    {
        return (bool) preg_match('/\.(tar\.gz|tgz)$/i', $path);
    }
}
