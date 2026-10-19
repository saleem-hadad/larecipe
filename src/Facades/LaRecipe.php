<?php

namespace LaRecipe\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \LaRecipe\Domains\Markdown\Components\ComponentBuilder component(string $name)
 * @method static void extendMarkdown(\Closure $extender)
 * @method static void extendSources(string $driver, \Closure $factory)
 * @method static void extendSearch(string $driver, \Closure $factory)
 * @method static void shareUser(\Closure $resolver)
 * @method static void head(\Closure $callback)
 * @method static \Illuminate\Support\HtmlString csrfField()
 * @method static void useCspNonce(?string $nonce)
 * @method static \LaRecipe\Domains\Content\DTOs\DocSet set(?string $key = null)
 * @method static void routes(\Closure $callback)
 * @method static void globalRoutes(\Closure $callback)
 * @method static void mcpTool(string $tool, bool $author = false)
 *
 * @see \LaRecipe\LaRecipe
 */
class LaRecipe extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \LaRecipe\LaRecipe::class;
    }
}
