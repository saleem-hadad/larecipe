<?php

namespace BinaryTorch\LaRecipe\Traits;

use Symfony\Component\DomCrawler\Crawler;

trait Searchable
{
    /**
     * @param  $query
     * @return mixed
     */
    public function search($version, $query)
    {
        $result = '';

        // get index
        $path = base_path(config('larecipe.docs.path').'/'.$version.'/index.md');

        // get paths
        $count = preg_match_all('/\(([^)]+)\)/', $this->documentation->files->get($path), $matches);

        if(! $count) {
            return null;
        }

        $result = [];
        
        foreach($matches[1] as $page) {
            $page = explode('{{version}}', $page)[1];
            
            $nodes = (new Crawler($this->documentation->get($version, $page)))
                    ->filter('h1, h2, h3')
                    ->each(function (Crawler $node, $i) {
                        return [$node->nodeName() => [$node->text()]];
                    });

            $nodes = array_merge_recursive(...$nodes);

            $result[$page] = $nodes;
        }

        // perform search or extract to indexer..

        return $result;
    }
}
