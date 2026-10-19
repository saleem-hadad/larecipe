<?php

namespace LaRecipe\Domains\Markdown\DTOs;

/**
 * What a fenced code block's info string asks for:
 *
 *   ```php title="app/Billing/Invoice.php" {2,4-6} showLineNumbers diff
 *
 * The language comes first; the rest is meta in any order. `[title]` works as
 * a short form of title="...".
 */
final readonly class CodeFence
{
    private const MAX_LINE = 10000;

    /**
     * @param  list<int>  $highlights  1-based line numbers
     */
    public function __construct(
        public ?string $language = null,
        public ?string $title = null,
        public array $highlights = [],
        public bool $lineNumbers = false,
        public bool $diff = false,
    ) {}

    public static function fromInfo(?string $info): self
    {
        $info = trim((string) $info);

        if ($info === '') {
            return new self;
        }

        preg_match('/^([^\s{]*)(.*)$/s', $info, $matches);
        $language = $matches[1] !== '' ? strtolower($matches[1]) : null;
        $rest = $matches[2];
        $title = null;

        if (preg_match('/\btitle=(?:"([^"]*)"|\'([^\']*)\'|(\S+))/', $rest, $found)) {
            $title = ($found[1] ?? '') !== '' ? $found[1] : (($found[2] ?? '') !== '' ? $found[2] : ($found[3] ?? ''));
            $rest = str_replace($found[0], '', $rest);
        } elseif (preg_match('/\[([^\]]+)\]/', $rest, $found)) {
            $title = $found[1];
            $rest = str_replace($found[0], '', $rest);
        }

        $highlights = [];

        if (preg_match('/\{([\d,\s-]+)\}/', $rest, $found)) {
            $highlights = self::lines($found[1]);
            $rest = str_replace($found[0], '', $rest);
        }

        $words = preg_split('/\s+/', strtolower(trim($rest))) ?: [];

        return new self(
            language: $language,
            title: $title !== null && trim($title) !== '' ? trim($title) : null,
            highlights: $highlights,
            lineNumbers: array_intersect($words, ['showlinenumbers', 'linenumbers', 'line-numbers']) !== [],
            diff: in_array('diff', $words, true),
        );
    }

    public function withTitle(?string $title): self
    {
        return new self($this->language, $title, $this->highlights, $this->lineNumbers, $this->diff);
    }

    public function withLanguage(?string $language): self
    {
        return new self($language, $this->title, $this->highlights, $this->lineNumbers, $this->diff);
    }

    /** The code block header: the title, else the language, else "text". */
    public function label(): string
    {
        return $this->title ?? $this->language ?? 'text';
    }

    /** True when lines starting with + or - mark additions and removals. */
    public function marksChanges(): bool
    {
        return $this->diff || $this->language === 'diff';
    }

    /** @return list<int> "2,4-6" becomes [2, 4, 5, 6] */
    private static function lines(string $spec): array
    {
        $lines = [];

        foreach (explode(',', $spec) as $part) {
            $part = trim($part);

            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $part, $range)) {
                $from = min((int) $range[1], self::MAX_LINE);
                $to = min((int) $range[2], self::MAX_LINE);

                for ($line = min($from, $to); $line <= max($from, $to); $line++) {
                    $lines[] = $line;
                }
            } elseif (ctype_digit($part)) {
                $lines[] = min((int) $part, self::MAX_LINE);
            }
        }

        $lines = array_values(array_unique(array_filter($lines, fn (int $line) => $line > 0)));
        sort($lines);

        return $lines;
    }
}
