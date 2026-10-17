<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * Move a page to a new path in the same version and locale.
 */
final class MovePageRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            ...$this->changeRules(),
            'path' => ['required', 'string', 'max:512'],
            'to' => ['required', 'string', 'max:255', 'regex:'.self::PATH_PATTERN],
        ];
    }

    public function messages(): array
    {
        return [
            'to.regex' => 'Use lowercase letters, numbers, dashes and slashes for the new path, for example billing/refunds.',
        ];
    }
}
