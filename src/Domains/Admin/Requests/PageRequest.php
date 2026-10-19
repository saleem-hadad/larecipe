<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * Read one page, by path, in a version and locale.
 */
final class PageRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            'path' => ['required', 'string', 'max:512'],
        ];
    }
}
