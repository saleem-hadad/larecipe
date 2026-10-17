<?php

namespace LaRecipe\Domains\Onboarding\Services;

/**
 * Reads and writes the JSON settings files agents use (.mcp.json,
 * .claude/settings.json, .codex/hooks.json), merging LaRecipe's entries into
 * what is there instead of replacing it.
 */
final class JsonSettings
{
    /**
     * Decode a settings file. Null means it exists but is not valid JSON,
     * which LaRecipe will not touch.
     *
     * @return array<string, mixed>|null
     */
    public function decode(?string $contents): ?array
    {
        if ($contents === null || trim($contents) === '') {
            return [];
        }

        $data = json_decode($contents, true);

        return is_array($data) ? $data : null;
    }

    /** Pretty JSON with two-space indentation, the way these files are usually written. */
    public function encode(array $data): string
    {
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return preg_replace_callback('/^( {4})+/m', fn (array $m) => str_repeat('  ', intdiv(strlen($m[0]), 4)), $json)."\n";
    }

    /**
     * Add a hook entry under an event unless a hook running the same LaRecipe
     * command is already there (in any matcher group).
     *
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $entry  {matcher, hooks: [{type, command}]}
     * @return array<string, mixed>
     */
    public function addHook(array $settings, string $event, array $entry, string $signature): array
    {
        $groups = is_array($settings['hooks'][$event] ?? null) ? $settings['hooks'][$event] : [];

        foreach ($groups as $group) {
            foreach ((array) ($group['hooks'] ?? []) as $hook) {
                if (is_array($hook) && str_contains((string) ($hook['command'] ?? ''), $signature)) {
                    return $settings;
                }
            }
        }

        $groups[] = $entry;
        $settings['hooks'] ??= [];
        $settings['hooks'][$event] = $groups;

        return $settings;
    }
}
