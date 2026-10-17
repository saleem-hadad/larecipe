<?php

namespace LaRecipe\Domains\Sync\Services;

/**
 * Finds a named region in a source file, for `path#region` sources and the
 * snippet includes that use the same markers.
 *
 * A region is the lines between `#region name` and the next `#endregion`,
 * behind any comment prefix (`// #region refund`, `# #region refund`,
 * `<!-- #region refund -->`), the convention VS Code and VitePress use.
 */
final class RegionLocator
{
    /** @return array{0: int, 1: int}|null 1-based first and last line, markers included */
    public function find(string $contents, string $region): ?array
    {
        $lines = preg_split('/\R/', $contents) ?: [];
        $start = null;
        $depth = 0;

        foreach ($lines as $index => $line) {
            $number = $index + 1;

            if ($start === null) {
                if (preg_match('/#region\s+'.preg_quote($region, '/').'\b/', $line)) {
                    $start = $number;
                }

                continue;
            }

            if (preg_match('/#region\b/', $line)) {
                $depth++;
            } elseif (preg_match('/#endregion\b/', $line)) {
                if ($depth === 0) {
                    return [$start, $number];
                }

                $depth--;
            }
        }

        return $start === null ? null : [$start, count($lines)];
    }

    /**
     * @param  array{0: int, 1: int}  $region
     * @param  list<array{0: int, 1: int}>  $changed
     */
    public function overlaps(array $region, array $changed): bool
    {
        foreach ($changed as [$from, $to]) {
            if ($from <= $region[1] && $to >= $region[0]) {
                return true;
            }
        }

        return false;
    }
}
