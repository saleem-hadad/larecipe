<?php

namespace LaRecipe\Domains\Onboarding\Services;

/**
 * Adds an MCP server to Codex's `.codex/config.toml` without a TOML library:
 * it appends one `[mcp_servers.<name>]` table and never edits the rest of
 * the file. An existing table with the same name is left as the user wrote it.
 */
final class CodexToml
{
    public function hasServer(?string $contents, string $name): bool
    {
        return $contents !== null && preg_match('/^\s*\[mcp_servers\.(?:"'.preg_quote($name, '/').'"|'.preg_quote($name, '/').')\]\s*$/m', $contents) === 1;
    }

    /** @param list<string> $args */
    public function addServer(?string $contents, string $name, string $command, array $args): string
    {
        $table = implode("\n", [
            "[mcp_servers.{$name}]",
            'command = '.$this->string($command),
            'args = ['.implode(', ', array_map($this->string(...), $args)).']',
        ]);

        $contents = rtrim((string) $contents);

        return ($contents === '' ? '' : $contents."\n\n").$table."\n";
    }

    private function string(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }
}
