<?php

namespace LaRecipe\Domains\Markdown\Services;

use Closure;
use LaRecipe\Domains\Markdown\DTOs\Snippet;

/**
 * Reads the code a snippet include points at, so samples come from the real
 * code and go stale loudly instead of quietly (the Real-Code Rule).
 *
 * Files resolve only inside the doc set's snippet root (the app's base path
 * for repository sets; nothing for database sets unless configured). Files
 * that commonly hold secrets are refused even inside the root: dotfiles such
 * as .env, keys and certificates, storage/ and Composer's auth.json. Docs must
 * never become a side door to configuration or environment values.
 *
 * Regions are marked in the source with comments:
 *
 *   // #region refund
 *   ...
 *   // #endregion
 */
final class SnippetResolver
{
    private const DENIED_EXTENSIONS = ['key', 'pem', 'p12', 'pfx', 'crt', 'cer', 'der', 'sqlite', 'db', 'env'];

    private const DENIED_FILES = ['auth.json'];

    private const DENIED_FOLDERS = ['storage/', 'bootstrap/cache/'];

    private const COMMENT = '^\s*(?:\/\/|#|\/\*+|<!--|\{\{--|--|;|\')?\s*';

    private const LANGUAGES = [
        'php' => 'php', 'js' => 'javascript', 'mjs' => 'javascript', 'cjs' => 'javascript', 'ts' => 'typescript',
        'tsx' => 'tsx', 'jsx' => 'jsx', 'vue' => 'vue', 'json' => 'json', 'yml' => 'yaml', 'yaml' => 'yaml',
        'sh' => 'bash', 'bash' => 'bash', 'md' => 'markdown', 'css' => 'css', 'scss' => 'scss', 'html' => 'html',
        'xml' => 'xml', 'sql' => 'sql', 'py' => 'python', 'rb' => 'ruby', 'go' => 'go', 'rs' => 'rust',
        'toml' => 'toml', 'ini' => 'ini', 'neon' => 'neon', 'txt' => 'text',
    ];

    /** @var array<string, ?string> roots resolved per doc set */
    private array $roots = [];

    /** @param (Closure(string): ?string)|null $rootFor the snippet root of a doc set, or null when includes are off */
    public function __construct(private readonly ?Closure $rootFor = null) {}

    /** The root for the page being rendered: the render context wins, then the doc set's setting. */
    public function root(RenderState $state): ?string
    {
        if (array_key_exists('snippets_root', $state->context)) {
            $root = $state->context['snippets_root'];

            return is_string($root) && $root !== '' ? $root : null;
        }

        if ($state->ref === null || $this->rootFor === null) {
            return null;
        }

        if (! array_key_exists($state->ref->set, $this->roots)) {
            $this->roots[$state->ref->set] = ($this->rootFor)($state->ref->set);
        }

        return $this->roots[$state->ref->set];
    }

    public function resolve(?string $root, string $path, ?string $region): Snippet
    {
        $base = $root !== null && $root !== '' ? realpath($root) : false;

        if ($base === false) {
            return new Snippet(Snippet::DISABLED);
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        $file = realpath($base.'/'.$path);

        if ($this->isDenied($path)) {
            return new Snippet(Snippet::DENIED);
        }

        if ($file === false) {
            return new Snippet(str_contains('/'.$path.'/', '/../') ? Snippet::DENIED : Snippet::MISSING_FILE);
        }

        // A symlink may point anywhere: check where the file really is.
        if (! str_starts_with($file, $base.DIRECTORY_SEPARATOR) || ! is_file($file) || $this->isDenied(substr($file, strlen($base) + 1))) {
            return new Snippet(Snippet::DENIED);
        }

        $contents = str_replace(["\r\n", "\r"], "\n", (string) file_get_contents($file));
        $hash = hash_file('xxh128', $file) ?: '';
        $code = $region === null ? $this->withoutMarkers($contents) : $this->region($contents, $region);

        if ($code === null) {
            return new Snippet(Snippet::MISSING_REGION, file: $file, hash: $hash);
        }

        return new Snippet(Snippet::FOUND, $code, $file, $hash, $this->language($path));
    }

    public function language(string $path): string
    {
        if (str_ends_with(strtolower($path), '.blade.php')) {
            return 'blade';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::LANGUAGES[$extension] ?? ($extension !== '' ? $extension : 'text');
    }

    private function isDenied(string $path): bool
    {
        $path = strtolower($path);

        foreach (explode('/', $path) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }

        foreach (self::DENIED_FOLDERS as $folder) {
            if (str_starts_with($path, $folder)) {
                return true;
            }
        }

        return in_array(basename($path), self::DENIED_FILES, true)
            || in_array(pathinfo($path, PATHINFO_EXTENSION), self::DENIED_EXTENSIONS, true);
    }

    /** The lines between `#region name` and its matching `#endregion`, or null when there is no such region. */
    private function region(string $contents, string $name): ?string
    {
        $inside = false;
        $depth = 0;
        $lines = [];

        foreach (explode("\n", $contents) as $line) {
            $opens = $this->opens($line);

            if (! $inside) {
                if ($opens === $name) {
                    [$inside, $depth] = [true, 1];
                }

                continue;
            }

            if ($opens !== null) {
                $depth++;

                continue;
            }

            if ($this->closes($line) && --$depth === 0) {
                break;
            }

            if (! $this->closes($line)) {
                $lines[] = $line;
            }
        }

        return $inside ? $this->dedent($lines) : null;
    }

    /** A whole-file include drops region markers: they are for the docs, not the reader. */
    private function withoutMarkers(string $contents): string
    {
        $lines = array_filter(explode("\n", $contents), fn (string $line) => $this->opens($line) === null && ! $this->closes($line));

        return $this->dedent(array_values($lines));
    }

    private function opens(string $line): ?string
    {
        return preg_match('/'.self::COMMENT.'#region\s+([\w.-]+)/', $line, $matches) ? $matches[1] : null;
    }

    private function closes(string $line): bool
    {
        return preg_match('/'.self::COMMENT.'#endregion\b/', $line) === 1;
    }

    /** @param list<string> $lines */
    private function dedent(array $lines): string
    {
        while ($lines !== [] && trim($lines[0]) === '') {
            array_shift($lines);
        }

        while ($lines !== [] && trim($lines[array_key_last($lines)]) === '') {
            array_pop($lines);
        }

        $indent = null;

        foreach ($lines as $line) {
            if (trim($line) !== '') {
                $indent = min($indent ?? PHP_INT_MAX, strlen($line) - strlen(ltrim($line, " \t")));
            }
        }

        return implode("\n", array_map(fn (string $line) => substr($line, min((int) $indent, strlen($line))), $lines));
    }
}
