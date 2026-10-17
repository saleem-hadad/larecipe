<?php

namespace LaRecipe\Domains\Sources\Services;

use DateTimeImmutable;
use LaRecipe\Domains\Sources\DTOs\ResolvedProvenance;

/**
 * Writes a set's compiled provenance index as a PHP file that returns an
 * array, like Laravel's own compiled caches, so OPcache keeps it in memory
 * and serving a page never runs git.
 */
final class ProvenanceIndexWriter
{
    public function write(string $path, ResolvedProvenance $resolved, DateTimeImmutable $at): void
    {
        $files = [];

        foreach ($resolved->files as $file => $provenance) {
            $files[$file] = $provenance->toArray();
        }

        ksort($files);

        $index = [
            'generated_at' => $at->format(DATE_ATOM),
            'method' => $resolved->method,
            'files' => $files,
        ];

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $temporary = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($temporary, "<?php\n\n// Compiled by LaRecipe (larecipe:provenance). Do not edit.\n\nreturn ".var_export($index, true).";\n");
        rename($temporary, $path);

        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($path, true);
        }
    }

    public function clear(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
