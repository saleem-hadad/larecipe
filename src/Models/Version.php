<?php

namespace BinaryTorch\LaRecipe\Models;

class Version extends Model
{
    protected $title;

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
