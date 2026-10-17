<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * Which production changes to export as a docs migration, and what to call it.
 */
final class ExportMigrationRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'revisions' => ['required', 'array', 'min:1', 'max:500'],
            'revisions.*' => ['required', 'integer', 'min:1'],
            'name' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 _-]+$/'],
            'summary' => ['nullable', 'string', 'max:240'],
        ];
    }

    /** @return list<string> */
    public function revisions(): array
    {
        return array_values(array_map('strval', (array) $this->input('revisions', [])));
    }
}
