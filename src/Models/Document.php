<?php

namespace BinaryTorch\LaRecipe\Models;

use BinaryTorch\LaRecipe\Exceptions\FillUnknownPropertyException;

class Document
{
    protected $seo;
    protected $path;
    protected $title;
    protected $content;
    protected $canonical;

    public static function create($attributes = [])
    {
        $document = new static();

        $document->fill($attributes);

        return $document;
    }

    public function fill($attributes = [])
    {
        foreach($attributes as $key => $value) {
            if(property_exists($this, $key)) {
                $this->{$key} = $value;
            }else {
                throw new FillUnknownPropertyException(sprintf(
                    'Fill [%s] to unknown property on [%s].',
                    $key, get_class($this)
                ));
            }
        }
    }

    public function toArray() 
    {
        return [
            'seo' => $this->seo,
            'path' => $this->path,
            'title' => $this->title,
            'content' => $this->content,
            'canonical' => $this->canonical
        ];
    }

    public function __get($key)
    {
        return property_exists($this, $key) ? $this->{$key} : null;
    }
}
