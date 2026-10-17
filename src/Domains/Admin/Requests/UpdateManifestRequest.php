<?php

namespace LaRecipe\Domains\Admin\Requests;

/**
 * The set settings screen saves the whole larecipe.json it shows (form or
 * raw JSON). The server turns it into a merge patch against production, so
 * removed keys are removed and nothing else is touched.
 */
final class UpdateManifestRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->changeRules(),
            'manifest' => ['present', 'array'],
        ];
    }

    /** @return array<string, mixed> */
    public function manifest(): array
    {
        return (array) $this->input('manifest', []);
    }
}
