<?php

namespace BinaryTorch\LaRecipe\Traits;

use Symfony\Component\DomCrawler\Crawler;

trait Indexable
{
    /**
     * @param  $version
     * @return mixed
     */
    public function index($version)
    {
        return $this->cache->remember(function () use ($version) {
            $pages = $this->getPages($version);

            $result = [];
            foreach($pages as $page) {
                $page = explode('{{version}}', $page)[1];
                $nodes = (new Crawler($this->get($version, $page)))
                        ->filter('h1, h2, h3')
                        ->each(function (Crawler $node, $i) {
                            return [$node->nodeName() => [$node->text()]];
                        });
                $nodes = array_merge_recursive(...$nodes);
                $result[$page] = $nodes;
            }

            return json_encode($result);
        }, 'larecipe.docs.'.$version.'.search');
    }

    /**
     * @param  $version
     * @return mixed
     */
    protected function getPages($version)
    {
        $path = base_path(config('larecipe.docs.path').'/'.$version.'/index.md');

        // match all markdown urls => [title](url)
        preg_match_all('/\(([^)]+)\)/', $this->files->get($path), $matches);

        return $matches[1];
    }
}
