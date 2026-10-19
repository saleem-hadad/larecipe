<?php

namespace LaRecipe\Domains\Onboarding\Services;

use Symfony\Component\Yaml\Yaml;

/**
 * Writes page frontmatter the way the docs and the skills show it: one key
 * per line, lists such as `sources` as a flow list on one line.
 */
final class FrontmatterWriter
{
    /** @param array<string, string|list<string>|null> $data */
    public function render(array $data): string
    {
        $lines = ['---'];

        foreach ($data as $key => $value) {
            if ($value === null || $value === []) {
                continue;
            }

            $lines[] = is_array($value)
                ? $key.': ['.implode(', ', array_map(fn (string $item) => (string) json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), $value)).']'
                : $key.': '.Yaml::dump($value);
        }

        $lines[] = '---';

        return implode("\n", $lines)."\n";
    }
}
