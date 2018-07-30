<?php

namespace BinaryTorch\LaRecipe\Models;

use BinaryTorch\LaRecipe\Traits\MarkdownParseable;

class Documentation
{
    use MarkdownParseable;
    
    /**
     * Check if the given version in the available version inside (config.larecipe.versions.published).
     *
     * @param $version
     * @return bool
     */
    public function isPublishedVersion($version)
    {
        $publishedVersions = config('larecipe.versions.published');
        
        return in_array($version, $publishedVersions);
    }
}
