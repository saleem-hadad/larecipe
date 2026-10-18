<?php

namespace LaRecipe\Domains\Markdown\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * The second layer for database sets (ARCHITECTURE.md 22): the rendered page
 * goes through symfony/html-sanitizer, because admin UI authors may be less
 * trusted than the codebase. The first layer (escaped raw HTML, allowlisted
 * attributes, safe links) already holds; this one makes sure that a gap in it
 * can never put a script, an event handler or a javascript: URL on a page.
 *
 * It keeps everything LaRecipe's renderers write: the component markup and
 * its classes, the data-* attributes islands and the code blocks read, heading
 * ids, inline code colors, and the forms host components may render.
 */
final class PageSanitizer
{
    /** Every data-* attribute LaRecipe's renderers write (COMPONENTS.md). */
    private const DATA_ATTRIBUTES = [
        'data-lr-component', 'data-lr-props', 'data-type', 'data-tone', 'data-cols', 'data-label', 'data-icon', 'data-display',
        'data-language', 'data-title', 'data-line', 'data-highlighted', 'data-diff', 'data-line-numbers', 'data-kind', 'data-missing',
    ];

    private ?HtmlSanitizer $sanitizer = null;

    /**
     * @param  list<string>  $verbatim  markup that must leave the sanitizer byte for byte: the CSRF field
     *                                  component fallbacks emit, which the response layer finds and fills in
     */
    public function __construct(private readonly array $verbatim = []) {}

    public function sanitize(string $html): string
    {
        // Kept fragments travel through the sanitizer as unguessable text, then come back as written.
        $tokens = [];

        foreach ($this->verbatim as $fragment) {
            $tokens[$fragment] = 'lrverbatim'.bin2hex(random_bytes(8));
        }

        $sanitized = ($this->sanitizer ??= new HtmlSanitizer($this->config()))->sanitize(strtr($html, $tokens));

        return strtr($sanitized, array_flip($tokens));
    }

    private function config(): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('form', ['action', 'method'])
            ->allowElement('input', ['type', 'name', 'value', 'placeholder', 'required', 'checked', 'disabled', 'autocomplete'])
            ->allowElement('textarea', ['name', 'rows', 'placeholder', 'required'])
            ->allowElement('select', ['name', 'required'])
            ->allowAttribute('class', '*')
            ->allowAttribute('aria-label', '*')
            ->allowAttribute('aria-hidden', '*')
            // Code tokens carry their colors inline (one code theme for light and dark pages).
            ->allowAttribute('style', ['span'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->allowRelativeLinks()
            ->allowMediaSchemes(['http', 'https'])
            ->allowRelativeMedias()
            ->withMaxInputLength(-1);

        foreach (self::DATA_ATTRIBUTES as $attribute) {
            $config = $config->allowAttribute($attribute, '*');
        }

        return $config;
    }
}
