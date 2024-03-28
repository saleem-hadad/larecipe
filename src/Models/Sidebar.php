<?php

namespace SaleemHadad\LaRecipe\Models;

class Sidebar extends Model
{    
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
            'content' => $this->content,
        ];
    }
}