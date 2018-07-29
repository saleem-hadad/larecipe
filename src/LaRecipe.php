<?php

namespace BinaryTorch\LaRecipe;

class LaRecipe
{
    /**
     * @param $markdown
     * @return string
     */
    public function parse($markdown)
    {
        return '<p>' . $markdown .'</p>';
    }
}