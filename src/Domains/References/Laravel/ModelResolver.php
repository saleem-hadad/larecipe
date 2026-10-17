<?php

namespace LaRecipe\Domains\References\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelInspector;
use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Contracts\ReferenceResolver;
use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;
use ReflectionClass;

/**
 * <Model name="Order" />: the table, its columns and types, casts, fillable
 * attributes and relations, through Eloquent's own model inspector.
 *
 * Only models in the allowlisted namespaces (references.models.allow) can be
 * described, so a page can never point the resolver at an arbitrary class.
 * Column defaults are left out: they are values, and this only shows shape.
 */
final class ModelResolver implements ListsReferenceTargets, ReferenceResolver
{
    /**
     * @param  list<string>  $allow  namespaces ("App\Models\*") or exact classes the docs may describe
     * @param  list<string>  $migrationPaths  folders whose migrations shape the schema, for the fingerprint
     */
    public function __construct(
        private readonly Application $app,
        private readonly array $allow,
        private readonly array $migrationPaths = [],
    ) {}

    public function kind(): string
    {
        return 'model';
    }

    public function resolve(string $target, array $props = []): ?Reference
    {
        $class = $this->classFor($target);

        if ($class === null) {
            return null;
        }

        $info = (new ModelInspector($this->app))->inspect($class);
        $columns = [];

        foreach ($info->attributes as $attribute) {
            if ($attribute['type'] === null) {
                continue;
            }

            $columns[] = [
                (string) $attribute['name'],
                (string) $attribute['type'],
                $attribute['nullable'] ? 'yes' : 'no',
                is_string($attribute['cast']) ? $attribute['cast'] : '',
                $attribute['fillable'] ? 'yes' : 'no',
            ];
        }

        $tables = [new ReferenceTable('Columns', ['Column', 'Type', 'Nullable', 'Cast', 'Fillable'], $columns)];
        $relations = [];

        foreach ($info->relations as $relation) {
            $relations[] = [(string) $relation['name'], (string) $relation['type'], (string) $relation['related']];
        }

        if ($relations !== []) {
            $tables[] = new ReferenceTable('Relations', ['Relation', 'Type', 'Related model'], $relations);
        }

        return new Reference('model', $target, $class, null, [['Table', (string) $info->table]], null, $tables);
    }

    public function targets(): array
    {
        $classes = [];

        foreach ($this->allow as $pattern) {
            $candidates = str_ends_with($pattern, '*') ? NamespaceClasses::in(rtrim($pattern, '\\*')) : [ltrim($pattern, '\\')];

            foreach ($candidates as $class) {
                if ($this->isModel($class)) {
                    $classes[] = $class;
                }
            }
        }

        sort($classes);

        return array_values(array_unique($classes));
    }

    /**
     * Changes when a model class or a migration changes. Reading the schema itself
     * would cost a query on every cached page view.
     */
    public function fingerprint(): string
    {
        $parts = $this->allow;

        foreach ($this->targets() as $class) {
            $file = (string) (new ReflectionClass($class))->getFileName();
            $parts[] = $class.':'.(is_file($file) ? filemtime($file) : '');
        }

        foreach ($this->migrationPaths as $path) {
            foreach (is_dir($path) ? (glob(rtrim($path, '/').'/*.php') ?: []) : [] as $migration) {
                $parts[] = basename($migration).':'.filemtime($migration);
            }
        }

        return hash('xxh128', implode("\n", $parts));
    }

    /** The allowed model class a target names: a full class name, or a short name inside an allowed namespace. */
    private function classFor(string $target): ?string
    {
        $target = ltrim(trim($target), '\\');

        foreach ($this->allow as $pattern) {
            $pattern = ltrim($pattern, '\\');

            $class = match (true) {
                str_ends_with($pattern, '*') && str_contains($target, '\\') => str_starts_with($target, rtrim($pattern, '*')) ? $target : null,
                str_ends_with($pattern, '*') => rtrim($pattern, '*').$target,
                default => $pattern === $target || class_basename($pattern) === $target ? $pattern : null,
            };

            if ($class !== null && $this->isModel($class)) {
                return $class;
            }
        }

        return null;
    }

    private function isModel(string $class): bool
    {
        return class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract();
    }
}
