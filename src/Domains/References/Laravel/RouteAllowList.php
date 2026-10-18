<?php

namespace LaRecipe\Domains\References\Laravel;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;
use Throwable;

/**
 * Which routes <Route> may describe (references.routes.allow).
 *
 * With no list, every route the app defines. Routes whose action lives in
 * vendor/ come from packages (an admin panel, a debug bar), so they are left
 * out, except LaRecipe's own, which its docs describe. A list chooses exactly:
 * routes whose name or URI matches one of its patterns (* is a wildcard),
 * package routes included when a pattern names them.
 */
final class RouteAllowList
{
    /** Route::view() and Route::redirect() routes are the app's own, though their controllers ship with Laravel. */
    private const DECLARATIVE = ['Illuminate\Routing\ViewController', 'Illuminate\Routing\RedirectController'];

    /**
     * @param  list<string>|null  $patterns  route names or URIs, null for every route the app defines
     * @param  string  $vendorPath  the folder packages are installed in
     */
    public function __construct(
        private readonly ?array $patterns,
        private readonly string $vendorPath,
    ) {}

    public function allows(Route $route): bool
    {
        if ($this->patterns !== null) {
            return $this->matches($route);
        }

        return $this->isLaRecipes($route) || ! $this->isFromPackage($route);
    }

    private function matches(Route $route): bool
    {
        $uri = trim($route->uri(), '/');

        foreach ($this->patterns as $pattern) {
            if (Str::is($pattern, (string) $route->getName()) || Str::is(trim($pattern, '/'), $uri)) {
                return true;
            }
        }

        return false;
    }

    /** The docs' own endpoints (feedback, MCP, schemas), all named larecipe.{set}.*. */
    private function isLaRecipes(Route $route): bool
    {
        return str_starts_with((string) $route->getName(), 'larecipe.');
    }

    /** True when the action is defined under vendor/, or cannot be found (fail closed). */
    private function isFromPackage(Route $route): bool
    {
        $uses = $route->getAction('uses');

        if (is_string($uses) && in_array(ltrim(Str::before($uses, '@'), '\\'), self::DECLARATIVE, true)) {
            return false;
        }

        try {
            // route:cache stores closures serialized; Laravel restores them the same way to run them.
            if (is_string($uses) && str_starts_with($uses, 'O:') && str_contains($uses, 'SerializableClosure')) {
                $uses = unserialize($uses)->getClosure();
            }

            $file = match (true) {
                $uses instanceof Closure => (new ReflectionFunction($uses))->getFileName(),
                is_string($uses) => (new ReflectionClass(Str::before($uses, '@')))->getFileName(),
                default => false,
            };
        } catch (Throwable) {
            return true;
        }

        return ! is_string($file) || str_starts_with($file, rtrim($this->vendorPath, '/').'/');
    }
}
