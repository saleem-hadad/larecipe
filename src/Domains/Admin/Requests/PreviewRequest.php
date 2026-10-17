<?php

namespace LaRecipe\Domains\Admin\Requests;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Content\DTOs\Page;

/**
 * A page as it stands in the editor, unsaved: for the live preview and the
 * inline validation issues. The path matters (relative links and images
 * resolve from it); a page without a path yet previews as "untitled".
 */
final class PreviewRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            'path' => ['nullable', 'string', 'max:512'],
            'frontmatter' => ['nullable', 'array'],
            'body' => ['nullable', 'string', 'max:1000000'],
        ];
    }

    public function page(): Page
    {
        $path = $this->input('path');

        return Page::make(
            $this->ref(is_string($path) && $path !== '' ? $path : 'untitled'),
            new Frontmatter((array) $this->input('frontmatter', [])),
            $this->markdown() ?? '',
        );
    }
}
