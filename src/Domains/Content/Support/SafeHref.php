<?php

namespace LaRecipe\Domains\Content\Support;

/**
 * The links content may put in navigation and chrome (navbar, footer, call to
 * action, the repository link): http, https and mailto URLs, and relative ones.
 *
 * Anything else is refused: javascript: and data: links run in the reader's
 * session, and in the server HTML they work before the UI loads or with
 * JavaScript off. A protocol-relative link (//host) is refused too, since it
 * leaves the site while looking like a path. Browsers ignore tabs, newlines
 * and leading control characters in a URL, so those never hide a scheme.
 */
final class SafeHref
{
    private const SCHEMES = ['http', 'https', 'mailto'];

    /** The href when a page may carry it, otherwise null. */
    public static function of(string $href): ?string
    {
        $bare = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $href);

        if ($bare === '' || preg_match('#^[/\\\\]{2}#', $bare) === 1) {
            return null;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $bare, $scheme) === 1 && ! in_array(strtolower($scheme[1]), self::SCHEMES, true)) {
            return null;
        }

        return trim($href);
    }

    /** True for an absolute http or https URL, such as a repository. */
    public static function isWebUrl(string $url): bool
    {
        return self::of($url) !== null && preg_match('#^https?://[^/\\\\]#i', trim($url)) === 1;
    }
}
