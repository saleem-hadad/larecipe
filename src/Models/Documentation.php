<?php

namespace BinaryTorch\LaRecipe\Models;

use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Traits\MarkdownParseable;

class Documentation
{
    use MarkdownParseable;

    /**
     * The filesystem implementation.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * Create a new documentation instance.
     *
     * @param  Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Get the documentation index page.
     *
     * @param  string  $version
     * @return string
     */
    public function getIndex($version)
    {
        $path = base_path(config('larecipe.docs.path') . '/' . $version . '/documentation.md');

        if ($this->files->exists($path)) {
            return $this->replaceLinks($version, $this->parse($this->files->get($path)));
        }

        return null;
    }

    /**
     * Get the given documentation page.
     *
     * @param  string  $version
     * @param  string  $page
     * @return string
     */
    public function get($version, $page)
    {
        $path = base_path(config('larecipe.docs.path') . '/' . $version . '/' . $page . '.md');

        if ($this->files->exists($path)) {
            return $this->replaceLinks($version, $this->parse($this->files->get($path)));
        }

        return null;
    }

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

    /**
     * Check if the given section exists.
     *
     * @param  string  $version
     * @param  string  $page
     * @return boolean
     */
    public function sectionExists($version, $page)
    {
        return $this->files->exists(
            base_path(config('larecipe.docs.path') . '/' . $version . '/' . $page . '.md')
        );
    }
}
