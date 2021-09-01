<?php

namespace BinaryTorch\LaRecipe;

class Config
{
    public static function path()
    {
        return config('larecipe.path');
    }

    public static function finder()
    {
        return config('larecipe.finder');
    }

    public static function isLanguageEnabled()
    {
        return config('larecipe.languages.enabled');
    }

    public static function defaultLanguage()
    {
        return config('larecipe.languages.default');
    }

    public static function publishedLanguages()
    {
        return config('larecipe.languages.published');
    }

    public static function isVersionEnabled()
    {
        return config('larecipe.versions.enabled');
    }

    public static function defaultVersion()
    {
        return config('larecipe.versions.default');
    }

    public static function publishedVersions()
    {
        return config('larecipe.versions.published');
    }

    public static function middleware()
    {
        return config('larecipe.middleware');
    }

    public static function isCacheEnabled()
    {
        return config('larecipe.cache.enabled');
    }

    public static function cachePeriod()
    {
        return config('larecipe.cache.duration');
    }
    
    public static function primaryColor()
    {
        return config('larecipe.branding.primary');
    }
    
    public static function secondaryColor()
    {
        return config('larecipe.branding.secondary');
    }
}