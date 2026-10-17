<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * Turn a draft or a conflict down. The reason is stored on the revision, so
 * the agent (or person) who wrote it can read why.
 */
final class RejectRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->input('reason');

        return is_string($reason) && trim($reason) !== '' ? trim($reason) : null;
    }
}
