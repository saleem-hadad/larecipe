<?php

namespace LaRecipe\Domains\Sync\Support;

/**
 * Decides whether a shell command pushes code or opens a pull request.
 *
 * This file must stay dependency-free: bin/larecipe-hook requires it directly,
 * before Composer's autoloader and long before Laravel, so that the hundreds
 * of ordinary Bash calls in an agent session cost a few milliseconds each.
 *
 * A command matches when any part of it (split on `&&`, `||`, `;`, `|`, `&`,
 * newlines and subshells, and inside `bash -c '...'`) runs a pattern such as
 * `git push` or `gh pr create`. Global options are skipped (`git -C app push`
 * matches), while a pattern word that only appears as an argument does not
 * (`git log --grep push` does not match).
 */
final class PushCommandDetector
{
    public const DEFAULT_PATTERNS = ['git push', 'gh pr create', 'glab mr create', 'hub pull-request'];

    /** Options that take the next word as their value, for the commands we know. */
    private const OPTIONS_WITH_VALUES = [
        '-C', '-c', '--git-dir', '--work-tree', '--namespace', '--exec-path', '--config-env',
        '-R', '--repo', '--hostname',
    ];

    /** Words that run the rest of the line as a command. */
    private const WRAPPERS = ['env', 'command', 'exec', 'time', 'nohup', 'sudo', 'xargs'];

    /** @var list<list<string>> */
    private array $patterns;

    /** @param list<string> $patterns */
    public function __construct(array $patterns = self::DEFAULT_PATTERNS)
    {
        $this->patterns = [];

        foreach ($patterns as $pattern) {
            $words = preg_split('/\s+/', trim((string) $pattern)) ?: [];

            if ($words !== [] && $words[0] !== '') {
                $this->patterns[] = $words;
            }
        }
    }

    public function matches(string $command): bool
    {
        return $this->matched($command) !== null;
    }

    /** The pattern the command matched ("git push"), or null. */
    public function matched(string $command, int $depth = 0): ?string
    {
        if ($depth > 3 || trim($command) === '') {
            return null;
        }

        foreach ($this->segments($command) as $segment) {
            $words = $this->words($segment);
            $words = $this->unwrap($words);

            if ($words === []) {
                continue;
            }

            $program = basename($words[0]);

            if (in_array($program, ['bash', 'sh', 'zsh', 'dash'], true)) {
                $index = array_search('-c', $words, true);

                if ($index !== false && isset($words[$index + 1])) {
                    $inner = $this->matched($words[$index + 1], $depth + 1);

                    if ($inner !== null) {
                        return $inner;
                    }
                }

                continue;
            }

            foreach ($this->patterns as $pattern) {
                if ($program === $pattern[0] && $this->startsWith($this->positionals(array_slice($words, 1)), array_slice($pattern, 1))) {
                    return implode(' ', $pattern);
                }
            }
        }

        return null;
    }

    /**
     * Split a command line into the simple commands it runs, respecting quotes.
     *
     * @return list<string>
     */
    private function segments(string $command): array
    {
        $segments = [];
        $current = '';
        $quote = null;
        $length = strlen($command);

        for ($i = 0; $i < $length; $i++) {
            $char = $command[$i];

            if ($quote !== null) {
                if ($char === '\\' && $quote === '"' && $i + 1 < $length) {
                    $current .= $char.$command[++$i];

                    continue;
                }

                if ($char === $quote) {
                    $quote = null;
                }

                $current .= $char;

                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $char.$command[++$i];

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $current .= $char;

                continue;
            }

            if (in_array($char, [';', '|', '&', "\n", '(', ')', '`'], true) || ($char === '$' && ($command[$i + 1] ?? '') === '(')) {
                $segments[] = $current;
                $current = '';

                continue;
            }

            if ($char === '{' || $char === '}') {
                $current .= ' ';

                continue;
            }

            $current .= $char;
        }

        $segments[] = $current;

        return array_values(array_filter(array_map('trim', $segments), fn (string $s) => $s !== ''));
    }

    /**
     * Split one simple command into words, removing quotes.
     *
     * @return list<string>
     */
    private function words(string $segment): array
    {
        $words = [];
        $current = '';
        $hasWord = false;
        $quote = null;
        $length = strlen($segment);

        for ($i = 0; $i < $length; $i++) {
            $char = $segment[$i];

            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                } elseif ($char === '\\' && $quote === '"' && $i + 1 < $length) {
                    $current .= $segment[++$i];
                } else {
                    $current .= $char;
                }

                continue;
            }

            if ($char === '"' || $char === "'") {
                $quote = $char;
                $hasWord = true;

                continue;
            }

            if ($char === '\\' && $i + 1 < $length) {
                $current .= $segment[++$i];
                $hasWord = true;

                continue;
            }

            if (ctype_space($char)) {
                if ($hasWord) {
                    $words[] = $current;
                    $current = '';
                    $hasWord = false;
                }

                continue;
            }

            $current .= $char;
            $hasWord = true;
        }

        if ($hasWord) {
            $words[] = $current;
        }

        return $words;
    }

    /**
     * Drop leading variable assignments (FOO=bar git push) and wrappers
     * (env, command, sudo ...) so the real program comes first.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function unwrap(array $words): array
    {
        $wrapped = false;

        while ($words !== []) {
            $first = $words[0];

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*=/', $first)) {
                array_shift($words);

                continue;
            }

            if (in_array(basename($first), self::WRAPPERS, true)) {
                $wrapped = true;
                array_shift($words);

                continue;
            }

            if ($wrapped && str_starts_with($first, '-')) {
                // Options of a wrapper we just removed (env -i, sudo -E).
                array_shift($words);

                continue;
            }

            break;
        }

        return $words;
    }

    /**
     * The words that are not options, skipping option values we know about.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    private function positionals(array $words): array
    {
        $positionals = [];
        $count = count($words);

        for ($i = 0; $i < $count; $i++) {
            $word = $words[$i];

            if ($word === '--') {
                break;
            }

            if (str_starts_with($word, '-')) {
                if (in_array($word, self::OPTIONS_WITH_VALUES, true)) {
                    $i++;
                }

                continue;
            }

            $positionals[] = $word;
        }

        return $positionals;
    }

    /**
     * @param  list<string>  $words
     * @param  list<string>  $prefix
     */
    private function startsWith(array $words, array $prefix): bool
    {
        foreach ($prefix as $index => $word) {
            if (($words[$index] ?? null) !== $word) {
                return false;
            }
        }

        return true;
    }
}
