<?php

namespace BinaryTorch\LaRecipe\Models;

class Document extends Model
{
    protected $fillable = ['seo', 'path', 'title', 'content', 'canonical'];

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'seo' => $this->seo,
            'path' => $this->path,
            'title' => $this->title,
            'content' => $this->content,
            'canonical' => $this->canonical
        ];
    }
}
