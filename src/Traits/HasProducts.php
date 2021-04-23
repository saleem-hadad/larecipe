<?php

namespace BinaryTorch\LaRecipe\Traits;

trait HasProducts
{
    /**
     * Gets all products of current documentation
     *
     * @param string[] $publishedVersions Documentation published versions
     * @return string[]|null
     */
    public function getProducts($publishedVersions)
    {
        $hasDefault = false;
        $products = [];
        foreach ($publishedVersions as $key => $version) {
            if (is_array($version))
                array_push($products, $key);
            else
                $hasDefault = true;
        }

        return empty($products) ? null : (!$hasDefault ?  $products : array_merge(array("larecipe_default"), $products));
    }
    /**
     * Gets all default published version (when is not any product)
     *
     * @param [type] $publishedVersions Documentation published versions
     * @return void
     */
    public function getVersions($publishedVersions)
    {
        $versions = array();

        foreach ($publishedVersions as $simpleVersion) {
            if (!is_array($simpleVersion))
                array_push($versions, (string)$simpleVersion);
        }
        return empty($versions) ? null : $versions;
    }
    /**
     * Gets all published version of product
     *
     * @param [type] $publishedVersions Documentation published versions
     * @param [type] $product Current product name
     * @return void
     */
    public function getProductVersions($publishedVersions, $product)
    {
        if (isset($publishedVersions[$product]))
            return $publishedVersions[$product];
        return null;
    }
}
