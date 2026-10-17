<?php

namespace LaRecipe\Domains\Sync\Services;

use LaRecipe\Domains\Sync\DTOs\SourcePattern;

/**
 * Matches `sources:` globs against project-relative file paths.
 *
 * The dialect is the one people already write in .gitignore and GitHub
 * Actions: `*` stays inside one folder, `**` crosses folders (and `**\/`
 * may match no folder at all), `?` is one character, `{a,b}` picks one of
 * several and `[abc]` is a character class. A path without wildcards
 * matches that file, or everything inside it when it names a folder.
 */
final class GlobMatcher
{
    /** @var array<string, string> */
    private array $compiled = [];

    public function matches(SourcePattern|string $pattern, string $path): bool
    {
        $glob = $pattern instanceof SourcePattern ? $pattern->glob : SourcePattern::parse($pattern)->glob;
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($glob === '') {
            return false;
        }

        if (strpbrk($glob, '*?[{') === false) {
            $glob = rtrim($glob, '/');

            return $path === $glob || str_starts_with($path, $glob.'/');
        }

        return (bool) preg_match($this->regex($glob), $path);
    }

    /**
     * @param  list<SourcePattern>  $patterns
     * @param  list<string>  $paths
     * @return array<string, list<string>> raw pattern => matching paths, only patterns that matched
     */
    public function matchAll(array $patterns, array $paths): array
    {
        $matches = [];

        foreach ($patterns as $pattern) {
            foreach ($paths as $path) {
                if ($this->matches($pattern, $path)) {
                    $matches[$pattern->raw][] = $path;
                }
            }
        }

        return $matches;
    }

    public function regex(string $glob): string
    {
        return $this->compiled[$glob] ??= '#^'.$this->translate($glob).'$#';
    }

    private function translate(string $glob): string
    {
        $regex = '';
        $length = strlen($glob);
        $braces = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $glob[$i];

            if ($char === '*') {
                if (($glob[$i + 1] ?? '') === '*') {
                    $i++;

                    // "**/" matches zero or more folders; a trailing "**" matches anything.
                    if (($glob[$i + 1] ?? '') === '/') {
                        $i++;
                        $regex .= '(?:.*/)?';
                    } else {
                        $regex .= '.*';
                    }
                } else {
                    $regex .= '[^/]*';
                }

                continue;
            }

            if ($char === '?') {
                $regex .= '[^/]';
            } elseif ($char === '{') {
                $braces++;
                $regex .= '(?:';
            } elseif ($char === '}' && $braces > 0) {
                $braces--;
                $regex .= ')';
            } elseif ($char === ',' && $braces > 0) {
                $regex .= '|';
            } elseif ($char === '[') {
                $regex .= $this->characterClass($glob, $i);
            } else {
                $regex .= preg_quote($char, '#');
            }
        }

        return $regex;
    }

    private function characterClass(string $glob, int &$i): string
    {
        $end = strpos($glob, ']', $i + 1);

        if ($end === false) {
            return preg_quote('[', '#');
        }

        $body = substr($glob, $i + 1, $end - $i - 1);
        $i = $end;
        $negate = str_starts_with($body, '!') || str_starts_with($body, '^');
        $body = $negate ? substr($body, 1) : $body;

        return '['.($negate ? '^' : '').str_replace(['\\', ']', '#'], ['\\\\', '\]', '\#'], $body).']';
    }
}
