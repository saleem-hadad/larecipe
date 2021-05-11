<?php

namespace BinaryTorch\LaRecipe\Models;

class Version extends Model
{
    protected $fillable = ['title'];

    protected $documents = [];

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
        ];
    }
}
