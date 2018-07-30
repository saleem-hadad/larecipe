<?php

namespace BinaryTorch\LaRecipe\Models;

use BinaryTorch\LaRecipe\Traits\MarkdownParseable;

class Documentation
{
    use MarkdownParseable;
    
    /**
     * @param $version
     * @param $page
     * @return string
     */
    public function get($version, $page)
    {
        return '<p>hello</p>';
    }
    
    /**
     * @param $version
     * @return bool
     */
    public function isPublishedVersion($version)
    {
        $publishedVersions = config('larecipe.versions.published');
        
        return in_array($version, $publishedVersions);
    }
}
