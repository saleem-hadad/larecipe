<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * Delete one asset, unless a page still uses it (or the person insists).
 */
final class DeleteAssetRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            'path' => ['required', 'string', 'max:1024'],
            'force' => ['nullable', 'boolean'],
        ];
    }
}
