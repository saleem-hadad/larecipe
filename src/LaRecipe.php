<?php

namespace BinaryTorch\LaRecipe;

class LaRecipe
{
    /**
     * All of the registered LaRecipe packages scripts.
     *
     * @var array
     */
    public static $scripts = [];

    /**
     * All of the registered LaRecipe packages CSS.
     *
     * @var array
     */
    public static $styles = [];

    /**
     * Get the current Larecipe version.
     *
     * @return string
     */
    public static function version()
    {
        return '2.0.0';
    }

    /**
     * Register the given script file with LaReipce.
     *
     * @param  string  $name
     * @param  string  $path
     * @return static
     */
    public static function script($name, $path)
    {
        static::$scripts[$name] = $path;

        return new static;
    }

    /**
     * Register the given CSS file with LaRecipe.
     *
     * @param  string  $name
     * @param  string  $path
     * @return static
     */
    public static function style($name, $path)
    {
        static::$styles[$name] = $path;

        return new static;
    }

    /**
     * Get all of the additional scripts.
     *
     * @return array
     */
    public static function allScripts()
    {
        return static::$scripts;
    }

    /**
     * Get all of the additional stylesheets.
     *
     * @return array
     */
    public static function allStyles()
    {
        $styles = static::$styles;

        if (config('larecipe.ui.theme_order')) {
            $newStyleOrder = [];
            foreach (config('larecipe.ui.theme_order') as $styleName) {
                $newStyleOrder[$styleName] = $styles[$styleName];
            }
            $styles = $newStyleOrder;
        }

        return $styles;
    }
}
