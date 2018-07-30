<?php

namespace BinaryTorch\LaRecipe\Models;

class Documentation
{
    /**
     * @var ParsedownExtra
     */
    private $parser;
    
    /**
     * Documentation constructor.
     */
    public function __construct(\ParsedownExtra $parser)
    {
        $this->parser = $parser;
    }
    
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
