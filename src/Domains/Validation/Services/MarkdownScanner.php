<?php

namespace LaRecipe\Domains\Validation\Services;

/**
 * Finds what some rules look for in a page's Markdown source (component tags,
 * snippet includes) while skipping code, fenced or inline: code is literal, so
 * an example in it is never reported as a problem.
 */
final class MarkdownScanner
{
    /** @return list<array{line: int, text: string}> body lines outside fenced code, 1-based, with inline code removed */
    public function lines(string $body): array
    {
        $lines = [];
        $fence = null;

        foreach (explode("\n", $body) as $index => $text) {
            // Fences may be indented (inside a list item); only a bare fence closes one.
            if (preg_match('/^\s*(`{3,}|~{3,})(.*)$/', $text, $matches)) {
                if ($fence === null) {
                    $fence = $matches[1];

                    continue;
                }

                if ($matches[1][0] === $fence[0] && strlen($matches[1]) >= strlen($fence) && trim($matches[2]) === '') {
                    $fence = null;

                    continue;
                }
            }

            if ($fence === null) {
                $lines[] = ['line' => $index + 1, 'text' => (string) preg_replace('/(`+)(?!`).*?(?<!`)\1(?!`)/', '', $text)];
            }
        }

        return $lines;
    }

    /** @return list<array{line: int, props: array<string, string|bool>}> every opening <Name ...> tag */
    public function tags(string $body, string $name): array
    {
        $tags = [];

        foreach ($this->lines($body) as $line) {
            if (preg_match_all('/<'.preg_quote($name, '/').'(\s[^>]*?)?\/?>/', $line['text'], $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $tags[] = ['line' => $line['line'], 'props' => $this->props($match[1] ?? '')];
                }
            }
        }

        return $tags;
    }

    /** @return list<array{line: int, path: string, region: ?string}> snippet includes: <<< @/app/Billing/Invoice.php#refund */
    public function snippets(string $body): array
    {
        $snippets = [];

        foreach ($this->lines($body) as $line) {
            if (preg_match('/^\s*<<<\s+@\/([^\s#{\[]+)(?:#([\w.-]+))?/', $line['text'], $matches)) {
                $snippets[] = ['line' => $line['line'], 'path' => $matches[1], 'region' => $matches[2] ?? null];
            }
        }

        return $snippets;
    }

    /** @return array<string, string|bool> */
    private function props(string $attributes): array
    {
        $props = [];

        preg_match_all('/([A-Za-z_][\w:.-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|\{([^}]*)\}|([^\s"\'>\/]+)))?/', $attributes, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);

        foreach ($matches as $match) {
            $props[$match[1]] = $match[2] ?? $match[3] ?? $match[4] ?? $match[5] ?? true;
        }

        return $props;
    }
}
