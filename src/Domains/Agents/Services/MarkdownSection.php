<?php

namespace LaRecipe\Domains\Agents\Services;

/**
 * Cuts one section out of a page's agent Markdown, so an agent that needs a
 * single heading does not pay for the whole page.
 *
 * A section is a heading and everything up to the next heading of the same or
 * a higher level. It matches by heading text or by its anchor id. The page's
 * YAML header stays on top, so the cut keeps its title and URL.
 */
final class MarkdownSection
{
    /** @return list<array{level: int, text: string, id: string, line: int}> */
    public function headings(string $markdown): array
    {
        $headings = [];
        $fence = null;

        foreach (explode("\n", $markdown) as $number => $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $matches)) {
                $marker = $matches[1][0];
                $fence = $fence === null ? $marker : ($fence === $marker ? null : $fence);

                continue;
            }

            if ($fence === null && preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $matches)) {
                $text = trim((string) preg_replace('/\s*\{#([^}]+)\}\s*$/', '', $matches[2]));
                $id = preg_match('/\{#([^}\s]+)\}\s*$/', $matches[2], $explicit) ? $explicit[1] : $this->id($text);
                $headings[] = ['level' => strlen($matches[1]), 'text' => $text, 'id' => $id, 'line' => $number];
            }
        }

        return $headings;
    }

    public function extract(string $markdown, string $section): ?string
    {
        $wanted = $this->id(ltrim(trim($section), '#'));
        $headings = $this->headings($markdown);
        $lines = explode("\n", $markdown);

        foreach ($headings as $index => $heading) {
            if ($heading['id'] !== $wanted && $this->id($heading['text']) !== $wanted) {
                continue;
            }

            $end = count($lines);

            foreach (array_slice($headings, $index + 1) as $next) {
                if ($next['level'] <= $heading['level']) {
                    $end = $next['line'];

                    break;
                }
            }

            $body = implode("\n", array_slice($lines, $heading['line'], $end - $heading['line']));

            return $this->header($markdown).rtrim($body)."\n";
        }

        return null;
    }

    /** The page's YAML header, kept on top of an extracted section. */
    private function header(string $markdown): string
    {
        return preg_match('/\A---\n.*?\n---\n/s', $markdown, $matches) ? $matches[0]."\n" : '';
    }

    private function id(string $text): string
    {
        $id = mb_strtolower(trim($text));
        $id = (string) preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $id);

        return trim((string) preg_replace('/[\s_-]+/u', '-', $id), '-');
    }
}
