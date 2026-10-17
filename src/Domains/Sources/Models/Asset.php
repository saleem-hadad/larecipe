<?php

namespace LaRecipe\Domains\Sources\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An image or file of a database set, stored on a Laravel filesystem disk.
 *
 * @property int $id
 * @property string $set
 * @property string $version
 * @property string $path
 * @property ?string $disk
 * @property string $disk_path
 * @property string $mime
 * @property int $size
 * @property ?string $hash
 */
class Asset extends Model
{
    protected $table = 'larecipe_assets';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['size' => 'integer'];
    }
}
