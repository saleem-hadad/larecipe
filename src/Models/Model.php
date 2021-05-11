<?php

namespace BinaryTorch\LaRecipe\Models;

use Illuminate\Contracts\Support\Arrayable;
use BinaryTorch\LaRecipe\Exceptions\FillUnknownPropertyException;

abstract class Model implements Arrayable
{
    public static function create($attributes = []): Model
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

    public function __get($key)
    {
        return property_exists($this, $key) ? $this->{$key} : null;
    }
}
