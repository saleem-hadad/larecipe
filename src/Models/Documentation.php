<?php

namespace BinaryTorch\LaRecipe\Models;

use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Traits\HasBladeParser;
use BinaryTorch\LaRecipe\Traits\HasMarkdownParser;
use Illuminate\Contracts\Cache\Repository as Cache;

class Documentation
{
    use HasMarkdownParser, HasBladeParser;

    /**
     * The filesystem implementation.
     *
     * @var Filesystem
     */
    protected $files;

    /**
     * The cache implementation.
     *
     * @var Cache
     */
    protected $cache;

    /**
     * Create a new documentation instance.
     *
     * @param  Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files, Cache $cache)
    {
        $this->files = $files;
        $this->cache = $cache;
    }

    /**
     * Get the documentation index page.
     *
     * @param  string  $version
     * @return string
     */
    public function getIndex($version)
    {
        $closure = function () use ($version) {
            $path = base_path(config('larecipe.docs.path').'/'.$version.'/index.md');

            if ($this->files->exists($path)) {
                $parsedContent = $this->parse($this->files->get($path));

                return $this->replaceLinks($version, $parsedContent);
            }

            return null;
        };

        if (! config('larecipe.cache.enabled')) {
            return $closure();
        }

        $cacheKey = 'larecipe.docs.'.$version.'.index';
        $cachePeriod = config('larecipe.cache.period');

        return $this->cache->remember($cacheKey, $cachePeriod, $closure);
    }

    /**
     * Get the given documentation page.
     *
     * @param  string  $version
     * @param  string  $page
     * @return string
     */
    public function get($version, $page, $data = [])
    {
        $closure = function () use ($version, $page, $data) {
            $path = base_path(config('larecipe.docs.path').'/'.$version.'/'.$page.'.md');

            if ($this->files->exists($path)) {
                $parsedContent = $this->parse($this->files->get($path));

                $parsedContent = $this->replaceLinks($version, $parsedContent);

                return $this->renderBlade($parsedContent, $data);
            }

            return null;
        };

        if (! config('larecipe.cache.enabled')) {
            return $closure();
        }

        $cacheKey = 'larecipe.docs.'.$version.'.'.$page;
        $cachePeriod = config('larecipe.cache.period');

        return $this->cache->remember($cacheKey, $cachePeriod, $closure);
    }

    /**
     * Replace the version and route placeholders.
     *
     * @param  string  $version
     * @param  string  $content
     * @return string
     */
    public static function replaceLinks($version, $content)
    {
        $content = str_replace('{{version}}', $version, $content);

        $content = str_replace('{{route}}', trim(config('larecipe.docs.route'), '/'), $content);

        return $content;
    }

    /**
     * Check if the given section exists.
     *
     * @param  string  $version
     * @param  string  $page
     * @return bool
     */
    public function sectionExists($version, $page)
    {
        return $this->files->exists(
            base_path(config('larecipe.docs.path').'/'.$version.'/'.$page.'.md')
        );
    }
}
