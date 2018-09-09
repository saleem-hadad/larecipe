<?php

namespace BinaryTorch\LaRecipe\Traits;

trait HasDocumentationAttributes
{
    public $title;
    public $index;
    public $content;
    public $docsRoute;
    public $canonical;
    public $sectionPage;
    public $defaultVersion;
    public $currentSection;
    public $statusCode = 200;
    public $publishedVersions;
    public $defaultVersionUrl;
}
