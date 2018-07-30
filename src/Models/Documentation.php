<?php

namespace BinaryTorch\LaRecipe\Models;

class Documentation
{
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
