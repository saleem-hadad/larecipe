<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * Delete a page. Its history stays, so a revert brings it back.
 */
final class DeletePageRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            ...$this->changeRules(),
            'path' => ['required', 'string', 'max:512'],
        ];
    }
}
