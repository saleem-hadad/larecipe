<?php

namespace LaRecipe\Domains\References\Laravel;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Contracts\ReferenceResolver;
use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use Throwable;

/**
 * <Route name="orders.store" />: method, URI and middleware from the route
 * collection, plus the fields of the form request the action type-hints and
 * the names of their rules.
 *
 * Only names are shown, never arguments: a rule such as in:{secret} or a
 * middleware parameter is often built from config, so "in" and "throttle" are
 * as far as a page goes. The domain is never shown, and RouteAllowList decides
 * which routes may be described at all. The route is never run, its
 * controller is never built (so controller middleware is not read), and the
 * form request is created bare, without the container, so it never validates
 * anything.
 */
final class RouteResolver implements ListsReferenceTargets, ReferenceResolver
{
    private const FORM_REQUEST = 'Illuminate\Foundation\Http\FormRequest';

    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
        private readonly RouteAllowList $allow,
        private readonly ?string $routeCacheFile = null,
    ) {}

    public function kind(): string
    {
        return 'route';
    }

    public function resolve(string $target, array $props = []): ?Reference
    {
        $route = $this->router->getRoutes()->getByName($target);

        if (! $route instanceof Route || ! $this->allow->allows($route)) {
            return null;
        }

        $methods = array_values(array_diff($route->methods(), ['HEAD']));
        $middleware = array_values(array_unique(array_map(fn (string $name) => $this->nameOf($name), array_filter($route->middleware(), 'is_string'))));
        $facts = [['Method', implode(', ', $methods)], ['URI', '/'.ltrim($route->uri(), '/')]];

        if ($middleware !== []) {
            $facts[] = ['Middleware', implode(', ', $middleware)];
        }

        $tables = [];
        $request = $this->formRequest($route);

        if ($request !== null) {
            $tables[] = new ReferenceTable(
                'Validation rules ('.class_basename($request).')',
                ['Field', 'Rules'],
                $this->rules($request, $route, $methods[0] ?? 'GET'),
            );
        }

        return new Reference('route', $target, ($methods[0] ?? 'GET').' /'.ltrim($route->uri(), '/'), null, $facts, null, $tables);
    }

    public function targets(): array
    {
        $names = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (is_string($route->getName()) && $route->getName() !== '' && $this->allow->allows($route)) {
                $names[] = $route->getName();
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }

    /** The route cache file when routes are cached, otherwise a hash of every route's declared shape. */
    public function fingerprint(): string
    {
        if ($this->routeCacheFile !== null && is_file($this->routeCacheFile)) {
            return 'cache:'.hash_file('xxh128', $this->routeCacheFile);
        }

        $shapes = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $shapes[] = implode(' ', [
                (string) $route->getName(),
                implode(',', $route->methods()),
                $route->uri(),
                (string) $route->getDomain(),
                implode(',', array_filter($route->middleware(), 'is_string')),
                $route->getActionName(),
            ]);
        }

        sort($shapes);

        return hash('xxh128', implode("\n", $shapes));
    }

    /** @return class-string|null the form request the route's action type-hints */
    private function formRequest(Route $route): ?string
    {
        try {
            $uses = $route->getAction('uses');
            $reflection = match (true) {
                $uses instanceof Closure => new ReflectionFunction($uses),
                is_string($uses) && str_contains($uses, '@') => new ReflectionMethod(...explode('@', $uses, 2)),
                is_string($uses) && method_exists($uses, '__invoke') => new ReflectionMethod($uses, '__invoke'),
                default => null,
            };
        } catch (Throwable) {
            return null;
        }

        foreach ($reflection?->getParameters() ?? [] as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && ! $type->isBuiltin() && is_subclass_of($type->getName(), self::FORM_REQUEST)) {
                return $type->getName();
            }
        }

        return null;
    }

    /** @return list<list<string>> each field with the names of its rules */
    private function rules(string $class, Route $route, string $method): array
    {
        try {
            $request = $class::create('/'.ltrim($route->uri(), '/'), $method);
            $request->setContainer($this->container);
            $request->setRouteResolver(fn () => $route);
            $rules = method_exists($request, 'rules') ? (array) $this->container->call([$request, 'rules']) : [];
        } catch (Throwable) {
            return [['', 'The rules depend on the request, so they cannot be shown here.']];
        }

        $rows = [];

        foreach ($rules as $field => $fieldRules) {
            $names = array_map(fn ($rule) => $this->ruleName($rule), is_string($fieldRules) ? explode('|', $fieldRules) : (array) $fieldRules);
            $rows[] = [(string) $field, implode(', ', $names)];
        }

        return $rows;
    }

    /** "required", "in", "exists": a rule's name, never its arguments. */
    private function ruleName(mixed $rule): string
    {
        return match (true) {
            is_string($rule) => $this->nameOf($rule),
            $rule instanceof Closure => 'custom',
            is_object($rule) && method_exists($rule, '__toString') => $this->nameOf((string) $rule),
            is_object($rule) => lcfirst(class_basename($rule)),
            default => 'custom',
        };
    }

    /** The part before the first colon ("throttle" for "throttle:5,1"), or "custom" when that is not a plain name. */
    private function nameOf(string $rule): string
    {
        $name = trim(explode(':', $rule, 2)[0]);

        return preg_match('/^[A-Za-z_][A-Za-z0-9_.\\\\-]*$/', $name) === 1 ? $name : 'custom';
    }
}
