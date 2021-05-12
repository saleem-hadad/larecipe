<?php

namespace BinaryTorch\LaRecipe\Models;

class SEO extends Model
{
    /**
     * @var string[]
     */
    protected $fillable = ['author', 'description', 'keywords', 'ogTitle', 'ogType', 'ogUrl', 'ogImage', 'ogDescription'];

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'author' => $this->author,
            'description' => $this->description,
            'keywords' => $this->keywords,
            'ogTitle' => $this->ogTitle,
            'ogType' => $this->ogType,
            'ogUrl' => $this->ogUrl,
            'ogImage' => $this->ogImage,
            'ogDescription' => $this->ogDescription,
        ];
    }
}
