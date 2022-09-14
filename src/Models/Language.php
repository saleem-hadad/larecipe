<?php

namespace BinaryTorch\LaRecipe\Models;

class Language extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = ['title'];

    /**
     * @var array
     */
    protected $versions = [];

    /**
     * @return array
     */
    public function getVersions(): array
    {
        return $this->versions;
    }

    /**
     * @param Version $version
     * @return $this
     */
    public function addVersion(Version $version): Language
    {
        $this->versions[] = $version;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
