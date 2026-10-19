<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * New navigation for one version and locale: the whole tree the page-tree
 * editor produced (navigation.json), or a patch of small operations (add,
 * move, remove, rename) when a page is created or deleted.
 */
final class UpdateNavigationRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            ...$this->changeRules(),
            'tree' => ['nullable', 'array', 'required_without:patch'],
            'patch' => ['nullable', 'array', 'required_without:tree'],
            'patch.*' => ['array'],
        ];
    }
}
