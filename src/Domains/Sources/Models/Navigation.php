<?php

namespace LaRecipe\Domains\Sources\Models;

use Illuminate\Database\Eloquent\Model;
use LaRecipe\Domains\Sources\Models\Casts\CanonicalJson;

/**
 * The live navigation.json of one version and locale of a database set.
 * A null tree means the navigation is derived from the pages (zero-config).
 *
 * @property int $id
 * @property string $set
 * @property string $version
 * @property string $locale
 * @property ?array $tree
 * @property ?string $content_hash
 * @property ?int $published_revision_id
 */
class Navigation extends Model
{
    protected $table = 'larecipe_navigation';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tree' => CanonicalJson::class,
            'published_revision_id' => 'integer',
        ];
    }
}
