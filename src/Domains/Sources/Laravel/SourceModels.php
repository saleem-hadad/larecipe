<?php

namespace LaRecipe\Domains\Sources\Laravel;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LaRecipe\Domains\Sources\Models;

/**
 * The Eloquent models of the database source, configurable the way Sanctum and
 * Cashier allow (larecipe.models.*), so a host can add scopes, tenancy or
 * observers by pointing a key at its own subclass.
 */
final class SourceModels
{
    public const DEFAULTS = [
        'page' => Models\Page::class,
        'navigation' => Models\Navigation::class,
        'manifest' => Models\Manifest::class,
        'revision' => Models\Revision::class,
        'asset' => Models\Asset::class,
        'docs_migration' => Models\DocsMigrationRecord::class,
    ];

    /** @param array<string, class-string<Model>> $classes */
    public function __construct(private readonly array $classes = self::DEFAULTS) {}

    /** @param array<string, class-string<Model>|null> $configured */
    public static function fromConfig(array $configured): self
    {
        return new self(array_merge(self::DEFAULTS, array_filter($configured)));
    }

    /** @return class-string<Model> */
    public function class(string $model): string
    {
        return $this->classes[$model] ?? self::DEFAULTS[$model];
    }

    public function query(string $model, ?string $connection = null): Builder
    {
        $class = $this->class($model);

        return $connection === null ? $class::query() : $class::on($connection);
    }

    public function make(string $model, ?string $connection = null): Model
    {
        $class = $this->class($model);
        $instance = new $class;

        if ($connection !== null) {
            $instance->setConnection($connection);
        }

        return $instance;
    }
}
