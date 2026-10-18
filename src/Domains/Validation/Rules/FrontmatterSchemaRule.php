<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Content\DTOs\Frontmatter;
use LaRecipe\Domains\Validation\Contracts\PageRule;
use LaRecipe\Domains\Validation\DTOs\PageContext;
use LaRecipe\Domains\Validation\DTOs\PageSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\SchemaValidator;
use LaRecipe\Domains\Validation\Services\Suggest;

/**
 * frontmatter.schema: YAML that does not parse, values of the wrong shape, and
 * unknown keys that look like typos of known ones ("descripton").
 *
 * Other unknown keys are fine: they stay on the server. Values for extensions
 * go under `meta:`, which reaches the UI as page meta.
 */
final class FrontmatterSchemaRule implements PageRule
{
    public function __construct(private readonly SchemaValidator $schemas) {}

    public function check(PageSnapshot $page, PageContext $context): array
    {
        $id = $context->pageId($page->page->ref->path);

        if ($page->yamlError !== null) {
            return [ValidationIssue::error('frontmatter.schema', 'The frontmatter is not valid YAML: '.$page->yamlError, $id, 1, 'Fix the YAML between the --- lines at the top of the page.', $page->file)];
        }

        $data = $page->page->frontmatter->toArray();
        $issues = [];

        foreach ($this->schemas->errors(SchemaValidator::FRONTMATTER, $data === [] ? new \stdClass : $data) as $error) {
            $issues[] = ValidationIssue::error('frontmatter.schema', 'Frontmatter '.$error, $id, file: $page->file, fix: 'See the frontmatter reference: '.SchemaValidator::FRONTMATTER.'.schema.json.');
        }

        foreach (array_keys($data) as $key) {
            if (in_array($key, Frontmatter::KNOWN_KEYS, true)) {
                continue;
            }

            $meant = Suggest::closest(str_replace('-', '_', (string) $key), Frontmatter::KNOWN_KEYS, 2);

            if ($meant !== null) {
                $issues[] = ValidationIssue::warning('frontmatter.schema', "Unknown frontmatter key \"{$key}\".", $id, null, "Rename \"{$key}\" to \"{$meant}\".", $page->file);
            }
        }

        return $issues;
    }
}
