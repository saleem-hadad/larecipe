<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * A reviewer's own version of a draft or a conflict (edit a draft, edit a
 * merge): a page's frontmatter and body, a navigation tree, or a manifest,
 * depending on what the draft changes.
 */
final class ReplaceDraftRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->changeRules(),
            'frontmatter' => ['nullable', 'array'],
            'body' => ['nullable', 'string', 'max:1000000'],
            'tree' => ['nullable', 'array'],
            'manifest' => ['nullable', 'array'],
        ];
    }
}
