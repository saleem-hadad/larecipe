<?php

namespace BinaryTorch\LaRecipe\Models;

use BinaryTorch\LaRecipe\Traits\MarkdownParseable;

class Documentation
{
    use MarkdownParseable;

    /**
     * Replace the version place-holder in links.
     *
     * @param  string  $version
     * @param  string  $content
     * @return string
     */
    public static function replaceLinks($version, $content)
    {
        return str_replace('{{version}}', $version, $content);
    }

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
