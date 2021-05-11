<?php

namespace BinaryTorch\LaRecipe\Models;

class Document extends Model
{
    protected $seo;
    protected $path;
    protected $title;
    protected $content;
    protected $canonical;

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
