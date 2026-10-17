<?php

namespace LaRecipe\Domains\Validation\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use LaRecipe\Domains\Validation\Services\SchemaValidator;

/**
 * Serves the JSON Schemas at /{prefix}/schemas/{name}.json, so editors and
 * agents can validate larecipe.json, navigation.json and frontmatter against
 * the copy that matches this install, without network access.
 */
final class SchemaController
{
    public const NAMES = ['larecipe', 'navigation', 'frontmatter', 'validate'];

    public function __invoke(Request $request): Response
    {
        $name = (string) $request->route('name');

        if (! in_array($name, self::NAMES, true)) {
            abort(404);
        }

        return new Response((string) file_get_contents(SchemaValidator::path($name)), 200, [
            'Content-Type' => 'application/schema+json',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
