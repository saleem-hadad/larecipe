<?php

namespace LaRecipe\Domains\Markdown\Parser;

/**
 * Parses the attributes of a component tag.
 *
 * Supported values: "double" and 'single' quoted strings, bare words, booleans
 * (a bare attribute is true), and JSON inside braces ({2}, {true}, {["a","b"]}).
 * JSX expressions are not supported: there is no JavaScript evaluation in PHP,
 * and LaRecipe never requires Node.
 */
final class PropsParser
{
    private const PATTERN = '/([A-Za-z_][-A-Za-z0-9_:.]*)(?:\s*=\s*(?:"((?:[^"\\\\]|\\\\.)*)"|\'([^\']*)\'|\{(.*?)\}(?=\s|$|\/)|([^\s"\'=<>`\/]+)))?/s';

    /** @return array<string, mixed> */
    public function parse(string $attributes): array
    {
        $props = [];

        if (trim($attributes) === '') {
            return $props;
        }

        preg_match_all(self::PATTERN, $attributes, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $match) {
            $name = $match[1];

            $props[$name] = match (true) {
                // Only \" and \\ are escapes, so class names keep their backslashes (App\Models\Order).
                $match[2] !== null => preg_replace('/\\\\(["\\\\])/', '$1', $match[2]),
                $match[3] !== null => $match[3],
                $match[4] !== null => $this->decodeJson($match[4]),
                $match[5] !== null => $this->scalar($match[5]),
                default => true,
            };
        }

        return $props;
    }

    private function decodeJson(string $value): mixed
    {
        $decoded = json_decode(trim($value), true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : trim($value);
    }

    private function scalar(string $value): mixed
    {
        return match ($value) {
            'true' => true,
            'false' => false,
            default => $value,
        };
    }
}
