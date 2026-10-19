<?php

namespace LaRecipe\Domains\Sync\Services;

/**
 * Reads and writes LaRecipe's section of a file the user owns (AGENTS.md,
 * CLAUDE.md, a git hook), between a start and an end marker.
 *
 * Text outside the markers is never touched: the block is replaced in place
 * when it exists and appended after a blank line when it does not.
 */
final class MarkedBlock
{
    public const START = '<!-- larecipe:start -->';

    public const END = '<!-- larecipe:end -->';

    public function __construct(
        private readonly string $start = self::START,
        private readonly string $end = self::END,
    ) {}

    public static function shell(): self
    {
        return new self('# larecipe:start', '# larecipe:end');
    }

    public function write(?string $contents, string $body): string
    {
        $block = $this->start."\n".rtrim($body, "\n")."\n".$this->end;
        $contents ??= '';
        $range = $this->range($contents);

        if ($range !== null) {
            return substr($contents, 0, $range[0]).$block.substr($contents, $range[1]);
        }

        if (trim($contents) === '') {
            return $block."\n";
        }

        return rtrim($contents, "\n")."\n\n".$block."\n";
    }

    /** The body between the markers, or null when there is no block. */
    public function read(?string $contents): ?string
    {
        $range = $contents === null ? null : $this->range($contents);

        if ($range === null) {
            return null;
        }

        $inner = substr($contents, $range[0] + strlen($this->start), $range[1] - $range[0] - strlen($this->start) - strlen($this->end));

        return trim($inner, "\n");
    }

    /** The file without the block (and the blank line before it), or null when there was none. */
    public function remove(string $contents): ?string
    {
        $range = $this->range($contents);

        if ($range === null) {
            return null;
        }

        $before = rtrim(substr($contents, 0, $range[0]), "\n");
        $after = ltrim(substr($contents, $range[1]), "\n");

        if ($before === '') {
            return $after;
        }

        return $after === '' ? $before."\n" : $before."\n\n".$after;
    }

    /** @return array{0: int, 1: int}|null byte offsets of the start marker and just past the end marker */
    private function range(string $contents): ?array
    {
        $start = strpos($contents, $this->start);

        if ($start === false) {
            return null;
        }

        $end = strpos($contents, $this->end, $start + strlen($this->start));

        return $end === false ? null : [$start, $end + strlen($this->end)];
    }
}
