<?php

namespace LaRecipe\Support;

/**
 * The files inside a project that LaRecipe never reads for anyone: every place
 * configuration, environment values, secrets or data usually live. One list,
 * shared by everything that opens project files (snippet includes in the docs,
 * the server-side docs agent), so a hole closed for one is closed for all.
 *
 * - every dotfile and dot folder (.env, .env.*, .git, .aws, .ssh, ...);
 * - config/, bootstrap/cache/, storage/, vendor/ and node_modules/;
 * - auth.json and PHPUnit files (they set environment values);
 * - private keys, certificates and key stores, SSH keys (id_*), PuTTY keys, and
 *   key or secret files without an extension (deploy_key, oauth-private);
 * - credential files (firebase-credentials.json), database files, dumps and logs,
 *   and JSON kept under database/, backups/ or dumps/.
 *
 * This is the No-Secrets Rule for anything that can open a file. Paths are
 * relative to the project root, with forward slashes.
 */
final class SecretFiles
{
    private const FOLDERS = ['config', 'bootstrap/cache', 'storage', 'vendor', 'node_modules'];

    private const FILES = ['auth.json', 'phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml'];

    private const EXTENSIONS = ['env', 'key', 'pem', 'p8', 'p12', 'pfx', 'ppk', 'crt', 'cer', 'der', 'jks', 'keystore', 'kdbx', 'sqlite', 'sqlite3', 'db', 'log', 'sql', 'dump'];

    /** Extensions of data and settings files, where a "credential" in the name means one. */
    private const DATA_EXTENSIONS = ['', 'json', 'yml', 'yaml', 'xml', 'txt', 'ini', 'conf', 'cfg', 'toml', 'csv'];

    /** Folders where JSON is data (seeds, exports, backups), not code. */
    private const DATA_FOLDERS = ['database', 'backup', 'backups', 'dump', 'dumps'];

    /** Why the file at this relative path may hold secrets, or null when it looks like ordinary code. */
    public static function reason(string $relative): ?string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');

        if ($relative === '') {
            return null;
        }

        $lower = strtolower($relative);
        $segments = explode('/', $lower);
        $name = (string) end($segments);
        $extension = str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : '';

        foreach ($segments as $segment) {
            if (str_starts_with($segment, '.')) {
                return "[{$relative}] is a dotfile or inside a dot folder (such as .env or .git). LaRecipe never reads them: they hold configuration, environment values and secrets.";
            }
        }

        foreach (self::FOLDERS as $folder) {
            if ($lower === $folder || str_starts_with($lower, $folder.'/')) {
                return "[{$relative}] is inside {$folder}/. LaRecipe never reads it: it holds configuration, environment values, secrets or third-party code. Describe settings by their key names, never their values.";
            }
        }

        $sshKey = str_starts_with($name, 'id_') && in_array($extension, ['', 'pub'], true);
        $keyLike = $extension === '' && preg_match('/(^|[-_])(privkey|key|private|secret)s?($|[-_])/', $name) === 1;
        $credential = str_contains($name, 'credential') && in_array($extension, self::DATA_EXTENSIONS, true);
        $dataJson = $extension === 'json' && array_intersect(array_slice($segments, 0, -1), self::DATA_FOLDERS) !== [];

        if (in_array($name, self::FILES, true) || $sshKey || in_array($extension, self::EXTENSIONS, true) || $keyLike || $credential || $dataJson) {
            return "[{$relative}] may hold credentials, keys, environment values or data, so LaRecipe never reads it.";
        }

        return null;
    }
}
