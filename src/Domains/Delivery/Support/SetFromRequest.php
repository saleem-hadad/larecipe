<?php

namespace LaRecipe\Domains\Delivery\Support;

use Illuminate\Http\Request;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\Services\SetRegistry;

/**
 * Which doc set a request belongs to. Every route in a set's group is named
 * "larecipe.{set}.*", so contributed routes need nothing else to find their set.
 */
final class SetFromRequest
{
    public function __construct(private readonly SetRegistry $sets) {}

    public function key(Request $request): string
    {
        $route = $request->route();
        $key = $route?->defaults['larecipeSet'] ?? null;

        if (is_string($key) && $key !== '') {
            return $key;
        }

        $name = (string) $route?->getName();

        if (preg_match('/^larecipe\.([^.]+)\./', $name, $matches)) {
            return $matches[1];
        }

        return $this->sets->default()->key;
    }

    public function set(Request $request): DocSet
    {
        return $this->sets->get($this->key($request));
    }
}
