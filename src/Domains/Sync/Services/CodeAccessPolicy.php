<?php

namespace LaRecipe\Domains\Sync\Services;

/**
 * Which project files the server-side docs agent may read.
 *
 * The agent reads code to write about it, and everything it reads goes to a
 * model provider. So the policy is a denylist of every place configuration,
 * environment values, secrets or customer data usually live, checked on the
 * path as asked and again on the real path behind any symlink:
 *
 * - anything outside the project root;
 * - every dotfile and dot folder (.env, .env.*, .git, .aws, .ssh, ...);
 * - config/ and bootstrap/cache/ (configuration, and the cached config with every value resolved);
 * - storage/, vendor/ and node_modules/;
 * - auth.json, PHPUnit files (they set environment values), private keys and certificates,
 *   database files and logs.
 *
 * Hosts can deny more with larecipe.ai.sync.deny; nothing can allow these.
 * This is the No-Secrets Rule applied to a reader that can open any file.
 */
final class CodeAccessPolicy
{
    private const DENIED_FOLDERS = ['config', 'bootstrap/cache', 'storage', 'vendor', 'node_modules'];

    private const DENIED_FILES = ['auth.json', 'phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml', 'id_rsa', 'id_dsa', 'id_ecdsa', 'id_ed25519', 'credentials.json'];

    private const DENIED_EXTENSIONS = ['env', 'key', 'pem', 'p8', 'p12', 'pfx', 'crt', 'cer', 'der', 'jks', 'keystore', 'kdbx', 'sqlite', 'sqlite3', 'db', 'log', 'sql', 'dump'];

    /** @param list<string> $extra more paths or globs to deny, relative to the root */
    public function __construct(
        private readonly string $root,
        private readonly array $extra = [],
    ) {}

    public function root(): string
    {
        return rtrim($this->root, '/');
    }

    /**
     * A clean path relative to the root (forward slashes, no "./"), or null
     * when the path leaves the root. "" is the root itself.
     */
    public function normalize(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));

        if (str_starts_with($path, $this->root().'/')) {
            $path = substr($path, strlen($this->root()) + 1);
        } elseif ($path === $this->root()) {
            $path = '';
        }

        if (str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:/', $path) || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return null;
        }

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                return null;
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /** Why this path may not be read, or null when it may. */
    public function denial(string $path): ?string
    {
        $relative = $this->normalize($path);

        if ($relative === null) {
            return "[{$path}] is outside the project. The docs agent only reads files inside the project.";
        }

        $denied = $this->denied($relative);

        if ($denied !== null) {
            return $denied;
        }

        // A symlink must not lead somewhere the path itself could not go.
        $real = realpath($this->absolute($relative));
        $realRoot = realpath($this->root()) ?: $this->root();

        if ($real === false) {
            return null;
        }

        if ($real !== $realRoot && ! str_starts_with($real, $realRoot.'/')) {
            return "[{$relative}] points outside the project. The docs agent only reads files inside the project.";
        }

        $target = $real === $realRoot ? '' : substr($real, strlen($realRoot) + 1);

        $denied = $target === $relative ? null : $this->denied($target);

        return $denied === null ? null : "[{$relative}] is a link to [{$target}]. {$denied}";
    }

    public function allows(string $path): bool
    {
        return $this->denial($path) === null;
    }

    public function absolute(string $relative): string
    {
        return $relative === '' ? $this->root() : $this->root().'/'.$relative;
    }

    private function denied(string $relative): ?string
    {
        if ($relative === '') {
            return null;
        }

        $segments = explode('/', $relative);
        $name = strtolower((string) end($segments));
        $lower = strtolower($relative);

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                return "[{$relative}] is a dotfile or inside a dot folder (such as .env or .git). LaRecipe never reads them: they hold configuration, environment values and secrets.";
            }
        }

        foreach (self::DENIED_FOLDERS as $folder) {
            if ($lower === $folder || str_starts_with($lower, $folder.'/')) {
                return "[{$relative}] is inside {$folder}/. LaRecipe never reads it: it holds configuration, environment values, secrets or third-party code. Describe settings by their key names, never their values.";
            }
        }

        $extension = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : '';

        if (in_array($name, self::DENIED_FILES, true) || str_starts_with($name, 'id_rsa') || in_array($extension, self::DENIED_EXTENSIONS, true)) {
            return "[{$relative}] may hold credentials, keys, environment values or data, so LaRecipe never reads it.";
        }

        foreach ($this->extra as $pattern) {
            $pattern = trim(str_replace('\\', '/', (string) $pattern), '/');

            if ($pattern !== '' && ($lower === strtolower($pattern) || str_starts_with($lower, strtolower($pattern).'/') || fnmatch($pattern, $relative, FNM_PATHNAME))) {
                return "[{$relative}] is denied by larecipe.ai.sync.deny.";
            }
        }

        return null;
    }
}
