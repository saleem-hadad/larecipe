<?php

namespace LaRecipe\Domains\Sources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LaRecipe\Domains\Sources\Models\Casts\CanonicalJson;

/**
 * What one docs migration did: per operation, the revision it created and the
 * one it replaced, and which operations became conflicts. `down()` reads it.
 *
 * @property int $id
 * @property string $migration
 * @property string $set
 * @property ?array $operations
 * @property ?array $conflicts
 * @property ?Carbon $applied_at
 */
class DocsMigrationRecord extends Model
{
    public $timestamps = false;

    protected $table = 'larecipe_docs_migrations';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'operations' => CanonicalJson::class,
            'conflicts' => CanonicalJson::class,
            'applied_at' => 'datetime',
        ];
    }
}
