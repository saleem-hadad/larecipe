<?php

namespace LaRecipe\Domains\Validation\Rules;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Validation\Contracts\SetRule;
use LaRecipe\Domains\Validation\DTOs\DocsSnapshot;
use LaRecipe\Domains\Validation\DTOs\ValidationIssue;
use LaRecipe\Domains\Validation\Services\SchemaValidator;
use LaRecipe\Domains\Validation\Services\Suggest;

/**
 * manifest.schema: larecipe.json that is not valid JSON or does not match the
 * schema, plus top-level keys that look like typos of known ones.
 * manifest.integrations: integrations in a database set's larecipe.json,
 * which are ignored (its analytics come from config/larecipe.php).
 * links.unsafe: navbar, footer and repository links that pages leave out.
 */
final class ManifestSchemaRule implements SetRule
{
    public function __construct(private readonly SchemaValidator $schemas) {}

    public function check(DocsSnapshot $docs): array
    {
        $file = $docs->manifestFile;

        if ($docs->manifestError !== null) {
            return [ValidationIssue::error('manifest.schema', 'larecipe.json is not valid JSON: '.$docs->manifestError, file: $file, fix: 'Fix the JSON syntax.')];
        }

        if ($docs->manifest === null) {
            return [ValidationIssue::warning('manifest.schema', 'This set has no larecipe.json, so it uses defaults for its name, theme and agent files.', file: $file, fix: 'Add a larecipe.json with at least a name and a description.')];
        }

        $issues = [];

        foreach ($this->schemas->errors(SchemaValidator::MANIFEST, $docs->manifest === [] ? new \stdClass : $docs->manifest) as $error) {
            $issues[] = ValidationIssue::error('manifest.schema', 'larecipe.json '.$error, file: $file, fix: 'See the manifest schema: '.SchemaValidator::MANIFEST.'.schema.json.');
        }

        foreach (Manifest::fromArray($docs->manifest)->refusedLinks() as $href) {
            $issues[] = ValidationIssue::unsafeLink($href, $file);
        }

        if ($docs->settings->driver !== 'repository' && isset($docs->manifest['integrations'])) {
            $issues[] = ValidationIssue::warning('manifest.integrations', 'A database set ignores integrations in larecipe.json, so these analytics are not loaded.', file: $file, fix: "Move them to sets.{$docs->set}.integrations in config/larecipe.php, then remove them from larecipe.json.");
        }

        $known = $this->schemas->knownKeys(SchemaValidator::MANIFEST);

        foreach (array_keys($docs->manifest) as $key) {
            $meant = in_array($key, $known, true) ? null : Suggest::closest((string) $key, $known, 2);

            if ($meant !== null) {
                $issues[] = ValidationIssue::warning('manifest.schema', "Unknown larecipe.json key \"{$key}\".", file: $file, fix: "Rename \"{$key}\" to \"{$meant}\".");
            }
        }

        return $issues;
    }
}
