<?php

namespace LaRecipe\Domains\Markdown\Services;

use LaRecipe\Domains\Markdown\DTOs\CodeFence;
use LaRecipe\Domains\Markdown\Support\PassTheme;
use LaRecipe\Support\CacheFormat;
use Phiki\Phiki;
use Phiki\Theme\ParsedTheme;
use Phiki\Theme\ThemeParser;
use Phiki\Token\HighlightedToken;
use Psr\SimpleCache\CacheInterface;
use Throwable;

/**
 * Highlights code with Phiki and the larecipe-pass theme, one span per line.
 *
 * Highlighting is the slowest step of rendering (about a millisecond per line
 * of PHP), so the output is cached per code block, keyed by its content, its
 * language and its line marks. Editing a paragraph re-renders the page, but
 * every unchanged code block comes from the cache.
 *
 * Unknown languages fall back to escaped text: a typo in a fence never breaks
 * a page.
 */
final class CodeHighlighter
{
    private const PLAIN = ['text', 'txt', 'plain', 'plaintext', 'none', 'output'];

    private const ALIASES = ['env' => 'dotenv', 'terminal' => 'shellscript', 'cli' => 'shellscript', 'vue-html' => 'vue'];

    private const MEMO_LIMIT = 256;

    private ?Phiki $phiki = null;

    private ?ParsedTheme $theme = null;

    /** @var array<string, string> highlighted blocks seen by this process */
    private array $memo = [];

    public function __construct(private readonly ?CacheInterface $cache = null) {}

    /** The lines of one code block as HTML, ready to sit inside <code>. */
    public function highlight(string $code, CodeFence $fence): string
    {
        $key = CacheFormat::psrPrefix().'code.'.hash('xxh128', implode("\0", [
            PassTheme::VERSION,
            $fence->language ?? '',
            $fence->marksChanges() ? 'diff' : '',
            implode(',', $fence->highlights),
            $code,
        ]));

        if (isset($this->memo[$key])) {
            return $this->memo[$key];
        }

        $html = $this->cached($key);

        if ($html === null) {
            $html = $this->render($code, $fence);
            $this->store($key, $html);
        }

        if (count($this->memo) >= self::MEMO_LIMIT) {
            $this->memo = [];
        }

        return $this->memo[$key] = $html;
    }

    /** Whether Phiki has a grammar for this language (after aliases). */
    public function knows(?string $language): bool
    {
        return $this->grammar($language) !== null;
    }

    private function render(string $code, CodeFence $fence): string
    {
        $lines = explode("\n", rtrim(str_replace(["\r\n", "\r"], "\n", $code), "\n"));
        [$lines, $changes] = $this->changes($lines, $fence);
        $tokens = $this->tokens(implode("\n", $lines), $fence->language);
        $html = [];

        foreach ($lines as $index => $line) {
            $number = $index + 1;
            $attributes = ' data-line="'.$number.'"'
                .(in_array($number, $fence->highlights, true) ? ' data-highlighted' : '')
                .(isset($changes[$index]) ? ' data-diff="'.$changes[$index].'"' : '');

            $content = $tokens === null ? $this->escape($line) : $this->spans($tokens[$index] ?? []);
            $html[] = '<span class="lr-line"'.$attributes.'>'.$content.'</span>';
        }

        return implode("\n", $html);
    }

    /**
     * Finds added and removed lines. In a ```diff block the markers stay (they
     * are the syntax). With the `diff` meta on another language, the marker is
     * removed so the line highlights as that language, and the gutter shows it.
     *
     * @param  list<string>  $lines
     * @return array{0: list<string>, 1: array<int, 'add'|'remove'>}
     */
    private function changes(array $lines, CodeFence $fence): array
    {
        if (! $fence->marksChanges()) {
            return [$lines, []];
        }

        $changes = [];
        $keepMarkers = $fence->language === 'diff';
        $markerColumn = $this->hasMarkerColumn($lines);

        foreach ($lines as $index => $line) {
            $marker = $line[0] ?? '';
            $isHeader = str_starts_with($line, '+++') || str_starts_with($line, '---');

            if (($marker === '+' || $marker === '-') && ! $isHeader) {
                $changes[$index] = $marker === '+' ? 'add' : 'remove';
            }

            if (! $keepMarkers && ($marker === '+' || $marker === '-' || ($markerColumn && $marker === ' '))) {
                $lines[$index] = substr($line, 1);
            }
        }

        // "- $b = 2;" style: drop the space between the marker column and the code.
        if (! $keepMarkers && $markerColumn && $this->allStartWithSpace($lines)) {
            $lines = array_map(fn (string $line) => $line === '' ? '' : substr($line, 1), $lines);
        }

        return [$lines, $changes];
    }

    /** True when the first column of every line is a diff marker or a space. */
    private function hasMarkerColumn(array $lines): bool
    {
        foreach ($lines as $line) {
            if ($line !== '' && ! in_array($line[0], ['+', '-', ' '], true)) {
                return false;
            }
        }

        return true;
    }

    private function allStartWithSpace(array $lines): bool
    {
        foreach ($lines as $line) {
            if ($line !== '' && $line[0] !== ' ') {
                return false;
            }
        }

        return true;
    }

    /** @return list<list<array{0: string, 1: string}>>|null [text, style] per token, per line; null for plain text */
    private function tokens(string $code, ?string $language): ?array
    {
        $grammar = $this->grammar($language);

        if ($grammar === null) {
            return null;
        }

        try {
            $highlighted = $this->phiki()->codeToHighlightedTokens($code, $grammar, ['default' => $this->theme()]);
        } catch (Throwable) {
            return null;
        }

        return array_map(fn (array $line) => array_map(fn (HighlightedToken $token) => [
            str_replace("\n", '', $token->token->text),
            $this->style($token),
        ], $line), array_values($highlighted));
    }

    private function style(HighlightedToken $token): string
    {
        $settings = $token->settings['default'] ?? null;

        if ($settings === null) {
            return '';
        }

        $styles = [];

        if ($settings->foreground !== null && strcasecmp($settings->foreground, PassTheme::TEXT) !== 0) {
            $styles[] = 'color:'.$settings->foreground;
        }

        foreach (explode(' ', (string) $settings->fontStyle) as $style) {
            $styles[] = match ($style) {
                'italic' => 'font-style:italic',
                'bold' => 'font-weight:600',
                'underline' => 'text-decoration:underline',
                default => null,
            };
        }

        return implode(';', array_filter($styles));
    }

    /**
     * Neighbouring tokens with the same style share one span, which keeps the
     * HTML (and the cached output) small.
     *
     * @param  list<array{0: string, 1: string}>  $tokens
     */
    private function spans(array $tokens): string
    {
        $runs = [];

        foreach ($tokens as [$text, $style]) {
            if ($text === '') {
                continue;
            }

            $last = array_key_last($runs);

            if ($last !== null && $runs[$last][1] === $style) {
                $runs[$last][0] .= $text;
            } else {
                $runs[] = [$text, $style];
            }
        }

        return implode('', array_map(
            fn (array $run) => $run[1] === '' ? $this->escape($run[0]) : '<span style="'.$run[1].'">'.$this->escape($run[0]).'</span>',
            $runs,
        ));
    }

    private function grammar(?string $language): ?string
    {
        if ($language === null || in_array($language, self::PLAIN, true)) {
            return null;
        }

        $language = self::ALIASES[$language] ?? $language;

        return $this->phiki()->environment->grammars->has($language) ? $language : null;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function phiki(): Phiki
    {
        return $this->phiki ??= new Phiki;
    }

    private function theme(): ParsedTheme
    {
        return $this->theme ??= (new ThemeParser)->parse(PassTheme::definition());
    }

    /** A broken cache store must never break a page, so cache failures fall through to rendering. */
    private function cached(string $key): ?string
    {
        try {
            $value = $this->cache?->get($key);
        } catch (Throwable) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    private function store(string $key, string $html): void
    {
        try {
            $this->cache?->set($key, $html);
        } catch (Throwable) {
            // Rendering already succeeded; the next render tries the cache again.
        }
    }
}
