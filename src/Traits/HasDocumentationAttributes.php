<?php

namespace BinaryTorch\LaRecipe\Traits;

trait HasDocumentationAttributes
{
    protected $title;
    protected $index;
    protected $version;
    protected $content;
    protected $canonical;
    protected $docsRoute;
    protected $sectionPage;
    protected $defaultVersion;
    protected $currentSection;
    protected $statusCode = 200;
    protected $publishedVersions;
    protected $defaultVersionUrl;

    /**
     * @return string
     */
    public function getTitleAttribute()
    {
        return $this->title;
    }

    /**
     * @return mixed
     */
    public function getIndexAttribute()
    {
        return $this->index;
    }

    /**
     * @return string
     */
    public function getVersionAttribute()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function getContentAttribute()
    {
        return $this->content;
    }

    /**
     * @return string
     */
    public function getCanonicalAttribute()
    {
        return $this->canonical;
    }

    /**
     * @return string
     */
    public function getDefaultVersionUrlAttribute()
    {
        return $this->defaultVersionUrl;
    }

    /**
     * @return string
     */
    public function getCurrentSectionAttribute()
    {
        return $this->currentSection;
    }

    /**
     * @return int
     */
    public function getStatusCodeAttribute()
    {
        return $this->statusCode;
    }

    /**
     * @return string
     */
    public function getPublishedVersionsAttribute()
    {
        return $this->publishedVersions;
    }
}
