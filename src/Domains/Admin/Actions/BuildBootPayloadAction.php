<?php

namespace LaRecipe\Domains\Admin\Actions;

use LaRecipe\Domains\Admin\Support\AuthorName;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\Locale;
use LaRecipe\Domains\Content\DTOs\Version;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\LaRecipe;
use LaRecipe\Support\Paths;

/**
 * Everything the admin app needs before its first request: which set it
 * manages, its versions and locales, who is signed in and what they may do,
 * where the API lives, the components the palette offers, and the JSON
 * Schemas the frontmatter, settings and navigation forms are generated from.
 *
 * The CSRF token is not here: the app sends Laravel's XSRF-TOKEN cookie back
 * as a header, like any SPA on the web guard.
 */
final class BuildBootPayloadAction
{
    public const SCHEMAS = ['frontmatter' => 'frontmatter', 'manifest' => 'larecipe', 'navigation' => 'navigation'];

    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly DocsUrls $urls,
    ) {}

    public function execute(DocSet $set, ?object $user): array
    {
        $writable = $set->config->driver === 'database';
        $base = $set->config->basePath().'/_admin';

        return [
            'set' => [
                'key' => $set->key,
                'name' => $set->manifest->name,
                'description' => $set->manifest->description,
                'driver' => $set->config->driver,
                'review' => $set->config->review,
                'versioned' => $set->isVersioned(),
                'multilingual' => $set->isMultilingual(),
            ],
            'versions' => array_map(fn (Version $version) => [
                'id' => $version->id,
                'label' => $version->label,
                'status' => $version->status->value,
                'default' => $version->default,
            ], $set->versions()),
            'locales' => array_map(fn (Locale $locale) => [
                'code' => $locale->code,
                'label' => $locale->label,
                'dir' => $locale->dir->value,
                'default' => $locale->default,
            ], $set->locales()),
            'user' => ['name' => AuthorName::of($user)],
            'permissions' => ['manage' => true, 'edit' => $writable, 'review' => $writable],
            'urls' => [
                'base' => $base,
                'api' => $base.'/api',
                'reader' => $this->urls->base($set),
            ],
            'components' => $this->components(),
            'schemas' => $this->schemas($set),
            'version' => LaRecipe::VERSION,
        ];
    }

    /** Components an author can place in a page (insert-only chrome components are left out). */
    private function components(): array
    {
        $components = array_filter($this->components->all(), fn (ComponentDefinition $definition) => ! $definition->chromeOnly);

        return array_values(array_map(fn (ComponentDefinition $definition) => $definition->toArray(), $components));
    }

    /**
     * The settings form offers no integrations where larecipe.json may not
     * declare them: a database set's analytics live in config/larecipe.php.
     */
    private function schemas(DocSet $set): array
    {
        $schemas = [];

        foreach (self::SCHEMAS as $key => $file) {
            $schemas[$key] = json_decode((string) file_get_contents(Paths::resources("schemas/{$file}.schema.json")), true);
        }

        if (! $set->config->allowsManifestIntegrations()) {
            unset($schemas['manifest']['properties']['integrations']);
        }

        return $schemas;
    }
}
