<?php

namespace BinaryTorch\LaRecipe;

use Symfony\Component\DomCrawler\Crawler;

class DocumentationRepository
{
    /**
     * Prepare the page title from the first h1 found.
     *
     * @return $this
     */
    protected function prepareTitle()
    {
        $this->title = (new Crawler($this->content))->filterXPath('//h1');
        $this->title = count($this->title) ? $this->title->text() : null;

        return $this;
    }

    /**
     * Prepare the current section page.
     *
     * @param $version
     * @param $page
     * @return $this
     */
    protected function prepareSection($version, $page)
    {
        if ($this->documentation->sectionExists($version, $page)) {
            $this->currentSection = $page;
        }

        return $this;
    }


    /**
     * Get the documentation index page.
     *
     * @param  string  $version
     * @return string
     */
    public function getIndex($version)
    {
        return $this->cache->remember(function() use($version) {
            $path = base_path(config('larecipe.docs.path').'/'.$version.'/index.md');

            if ($this->files->exists($path)) {
                $parsedContent = $this->parse($this->replaceNewLinks($version, $this->files->get($path)));

                $parsedContent = $this->replaceLinks($version, $parsedContent);

                return $parsedContent;
            }

            return null;
        }, 'larecipe.docs.'.$version.'.index');
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

        $content = str_replace('"#', '"'.request()->getRequestUri().'#', $content);

        return $content;
    }

    public static function replaceNewLinks($version, $content)
    {
        $content = str_replace('{{version}}', $version, $content);

        return str_replace('{{route}}', trim(config('larecipe.docs.route'), '/'), $content);
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

