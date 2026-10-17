<?php

namespace LaRecipe\Domains\Sources\Services;

/**
 * Writes PHP values the way a person would in a migration file: short array
 * syntax, short lists of scalars on one line, everything else one entry per
 * line. var_export is exact but unreadable in a PR diff.
 */
final class PhpValue
{
    public static function export(mixed $value, int $indent = 0, bool $multiline = false): string
    {
        if (! is_array($value)) {
            return self::scalar($value);
        }

        if ($value === []) {
            return '[]';
        }

        $list = array_is_list($value);

        if (! $multiline && $list && count(array_filter($value, 'is_array')) === 0) {
            $inline = '['.implode(', ', array_map(self::scalar(...), $value)).']';

            if (strlen($inline) <= 80 && ! str_contains($inline, "\n")) {
                return $inline;
            }
        }

        $pad = str_repeat(' ', $indent + 4);
        $lines = [];

        foreach ($value as $key => $item) {
            $lines[] = $pad.($list ? '' : self::scalar($key).' => ').self::export($item, $indent + 4).',';
        }

        return "[\n".implode("\n", $lines)."\n".str_repeat(' ', $indent).']';
    }

    public static function string(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }

    private static function scalar(mixed $value): string
    {
        return match (true) {
            is_string($value) => self::string($value),
            is_bool($value) => $value ? 'true' : 'false',
            $value === null => 'null',
            is_float($value) => var_export($value, true),
            default => (string) $value,
        };
    }
}
