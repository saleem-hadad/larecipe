<?php

namespace LaRecipe\Domains\Sources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LaRecipe\Domains\Sources\Models\Casts\CanonicalJson;

/**
 * One page of a database set as readers see it now. Its history lives in
 * larecipe_revisions; a page that is only a draft so far has no published
 * revision, and a deleted page keeps its row (and history) with deleted_at set.
 *
 * Configurable with larecipe.models.page, so a host can add scopes, tenancy or
 * observers by extending it.
 *
 * @property int $id
 * @property string $set
 * @property string $version
 * @property string $locale
 * @property string $path
 * @property string $title
 * @property ?string $description
 * @property ?array $frontmatter
 * @property ?string $body
 * @property ?string $content_hash
 * @property ?int $published_revision_id
 * @property ?Carbon $deleted_at
 */
class Page extends Model
{
    protected $table = 'larecipe_pages';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'frontmatter' => CanonicalJson::class,
            'published_revision_id' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }
}
