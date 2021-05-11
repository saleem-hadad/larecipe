<?php

namespace BinaryTorch\LaRecipe\Models;

use Illuminate\Contracts\Support\Arrayable;

abstract class Model implements Arrayable
{
    protected $attributes = [];
    protected $fillable = [];

    public static function create($attributes = []): Model
    {
        $document = new static();

        $document->fill($attributes);

        return $document;
    }

    public function fill($attributes = [])
    {
        $fillFromArray = array_intersect_key($attributes, array_flip($this->fillable));

        foreach($fillFromArray as $key => $value) {
            $this->attributes[$key] = $value;
        }
    }

    public function __get($key)
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null;
    }
}
