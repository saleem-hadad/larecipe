<?php

namespace LaRecipe\Domains\Admin\Requests;

use LaRecipe\Domains\Content\DTOs\Frontmatter;

/**
 * Create (POST) or update (PUT) one page: its frontmatter as an object, its
 * Markdown body, and how to save it (publish, or a draft for review). New
 * pages get a lowercase, slash-separated path; an update names the page it
 * read and the content hash it read (based_on).
 */
final class SavePageRequest extends AdminRequest
{
    public function rules(): array
    {
        return [
            ...$this->scopeRules(),
            ...$this->changeRules(),
            'path' => $this->isMethod('post')
                ? ['required', 'string', 'max:255', 'regex:'.self::PATH_PATTERN]
                : ['required', 'string', 'max:512'],
            'frontmatter' => ['nullable', 'array'],
            'body' => ['nullable', 'string', 'max:1000000'],
        ];
    }

    public function messages(): array
    {
        return [
            'path.regex' => 'Use lowercase letters, numbers, dashes and slashes for the path, for example billing/refunds.',
        ];
    }

    public function frontmatter(): Frontmatter
    {
        return new Frontmatter((array) $this->input('frontmatter', []));
    }

    public function body(): string
    {
        return $this->markdown() ?? '';
    }
}
