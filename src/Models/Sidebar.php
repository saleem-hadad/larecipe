<?php

namespace BinaryTorch\LaRecipe\Models;

use BinaryTorch\LaRecipe\Traits\HasMarkdownParser;

class Sidebar extends Model
{
    use HasMarkdownParser;
    
    /**
     * @var string[]
     */
    protected $fillable = ['content'];

    /**
     * @return array
     */
    public function toArray()
    {
        return [
            'content' => $this->parseMarkdown($this->content),
        ];
    }
}