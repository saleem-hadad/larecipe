<?php

namespace LaRecipe\Domains\Validation\Services;

use LaRecipe\Support\Paths;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;

/**
 * Validates larecipe.json, navigation.json and frontmatter against the JSON
 * Schemas in resources/schemas, the same files the docs site serves to IDEs.
 */
final class SchemaValidator
{
    public const MANIFEST = 'larecipe';

    public const NAVIGATION = 'navigation';

    public const FRONTMATTER = 'frontmatter';

    public const REPORT = 'validate';

    /** @var array<string, object> */
    private array $schemas = [];

    /**
     * @return list<string> readable problems, "at /versions/0: The required properties (id) are missing", empty when valid
     */
    public function errors(string $schema, mixed $data): array
    {
        $validator = new Validator;
        $validator->setMaxErrors(10);

        $result = $validator->validate($this->toJson($data), $this->schema($schema));

        if ($result->isValid() || ($error = $result->error()) === null) {
            return [];
        }

        return $this->flatten($error);
    }

    /** @return list<string> the property names a schema documents at its top level */
    public function knownKeys(string $schema): array
    {
        return array_keys((array) ($this->schema($schema)->properties ?? []));
    }

    public static function path(string $schema): string
    {
        return Paths::resources('schemas/'.$schema.'.schema.json');
    }

    private function schema(string $name): object
    {
        return $this->schemas[$name] ??= json_decode((string) file_get_contents(self::path($name)), false, 512, JSON_THROW_ON_ERROR);
    }

    /** Opis wants objects for JSON objects and arrays for lists, as json_decode() without assoc gives. */
    private function toJson(mixed $data): mixed
    {
        return json_decode((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    /** @return list<string> */
    private function flatten(ValidationError $error): array
    {
        $messages = [];

        foreach ((new ErrorFormatter)->format($error, true) as $pointer => $errors) {
            foreach ((array) $errors as $message) {
                $messages[] = ($pointer === '/' || $pointer === '' ? '' : "at {$pointer}: ").$message;
            }
        }

        return array_values(array_unique($messages));
    }
}
