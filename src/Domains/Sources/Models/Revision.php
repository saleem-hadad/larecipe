<?php

namespace LaRecipe\Domains\Sources\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LaRecipe\Domains\Sources\Models\Casts\CanonicalJson;

/**
 * One change to a page, a navigation or a manifest of a database set, with
 * the full state it leads to (payload) and who made it, through which channel.
 * Revisions are never edited after the fact, except for their review status.
 *
 * @property int $id
 * @property string $set
 * @property string $subject_type
 * @property int $subject_id
 * @property string $operation
 * @property ?string $version
 * @property ?string $locale
 * @property ?string $path
 * @property ?string $path_before
 * @property ?array $payload
 * @property ?string $content_hash
 * @property ?string $based_on_hash
 * @property ?int $replaces_id
 * @property ?array $conflict
 * @property string $status
 * @property string $actor
 * @property ?string $actor_name
 * @property ?string $actor_ref
 * @property ?string $channel
 * @property ?string $migration
 * @property ?string $summary
 * @property ?string $reviewed_by
 * @property ?Carbon $reviewed_at
 * @property ?string $review_note
 * @property ?Carbon $published_at
 * @property ?Carbon $created_at
 */
class Revision extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'larecipe_revisions';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => CanonicalJson::class,
            'conflict' => CanonicalJson::class,
            'subject_id' => 'integer',
            'replaces_id' => 'integer',
            'reviewed_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
