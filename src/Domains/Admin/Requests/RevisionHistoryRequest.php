<?php

namespace LaRecipe\Domains\Admin\Requests;

use Illuminate\Validation\Rule;
use LaRecipe\Domains\Sources\DTOs\Subject;
use LaRecipe\Domains\Sources\Enums\SubjectType;

/**
 * Whose history to list: one page, the navigation of a version and locale,
 * or the set's manifest.
 */
final class RevisionHistoryRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            'type' => ['required', Rule::enum(SubjectType::class)],
            'path' => ['nullable', 'string', 'max:512', 'required_if:type,page'],
        ];
    }

    public function subject(): Subject
    {
        return match (SubjectType::from((string) $this->input('type'))) {
            SubjectType::Page => Subject::page($this->ref()),
            SubjectType::Navigation => Subject::navigation($this->docSet()->key, $this->version(), $this->locale()),
            SubjectType::Manifest => Subject::manifest($this->docSet()->key),
        };
    }
}
