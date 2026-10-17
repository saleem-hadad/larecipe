<?php

namespace LaRecipe\Domains\Sources\Models;

use Illuminate\Database\Eloquent\Model;
use LaRecipe\Domains\Sources\Models\Casts\CanonicalJson;

/**
 * The live larecipe.json of a database set.
 *
 * @property int $id
 * @property string $set
 * @property ?array $manifest
 * @property ?string $content_hash
 * @property ?int $published_revision_id
 */
class Manifest extends Model
{
    protected $table = 'larecipe_manifest';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'manifest' => CanonicalJson::class,
            'published_revision_id' => 'integer',
        ];
    }
}
