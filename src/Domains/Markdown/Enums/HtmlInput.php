<?php

namespace LaRecipe\Domains\Markdown\Enums;

/**
 * What happens to raw HTML written in a doc set's Markdown (ARCHITECTURE.md
 * 6.2 and 22). Component tags are never raw HTML: their own parser reads them.
 */
enum HtmlInput: string
{
    /** The default: raw HTML is shown as text. */
    case Escape = 'escape';

    /** A repository set opted in with html_input: allow; raw HTML passes as written. */
    case Allow = 'allow';

    /**
     * Database sets: raw HTML is escaped, and the rendered page also goes through
     * an HTML sanitizer, since admin UI authors may be less trusted than the codebase.
     */
    case Sanitize = 'sanitize';
}
