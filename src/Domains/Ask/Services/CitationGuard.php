<?php

namespace LaRecipe\Domains\Ask\Services;

/**
 * Keeps Ask AI's citations honest: only links to pages the reader may see in
 * this set count as citations. Anything else the model wrote (an invented URL,
 * a page from another version, a hidden page) is never shown as a source.
 */
final class CitationGuard
{
    /**
     * @param  array<string, array{url: string, title: string}>  $known  normalized path => the page's URL and title
     */
    public function __construct(private readonly array $known) {}

    /**
     * The known pages a text links to, in the order it cites them.
     *
     * @return list<array{url: string, title: string}>
     */
    public function citations(string $text): array
    {
        preg_match_all('/\]\(\s*<?([^)\s>]+)>?(?:\s+"[^"]*")?\s*\)|(https?:\/\/[^\s)<>"\]]+)/', $text, $matches, PREG_SET_ORDER);

        $cited = [];

        foreach ($matches as $match) {
            $page = $this->known[self::normalize($match[1] !== '' ? $match[1] : ($match[2] ?? ''))] ?? null;

            if ($page !== null) {
                $cited[$page['url']] = $page;
            }
        }

        return array_values($cited);
    }

    /** One key for every form of a page's address: absolute or relative, with .md, a slash or an #anchor. */
    public static function normalize(string $url): string
    {
        $path = (string) (parse_url(trim($url), PHP_URL_PATH) ?? '');
        $path = (string) preg_replace('/\.md$/i', '', rawurldecode($path));
        $path = (string) preg_replace('#/index$#', '', $path);

        return '/'.trim($path, '/');
    }
}
