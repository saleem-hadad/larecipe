<?php

namespace LaRecipe\Domains\Sync\Laravel;

use Composer\Autoload\ClassLoader;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Console\ClosureCommand;
use Illuminate\Routing\Router;
use LaRecipe\Domains\Sync\Contracts\Codebase;
use ReflectionClass;
use Throwable;

/**
 * Answers "which file defines this?" from the running app: Composer's class
 * map for classes, the console kernel for artisan commands and the router
 * for route names. Only files inside the project count; a vendor class or a
 * framework command is not something this app's docs should track.
 */
final class LaravelCodebase implements Codebase
{
    public function __construct(
        private readonly Kernel $artisan,
        private readonly Router $router,
        private readonly string $root,
    ) {}

    public function classFile(string $class): ?string
    {
        $class = ltrim($class, '\\');

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            $file = $loader->findFile($class);

            if (is_string($file)) {
                return $this->project($file);
            }
        }

        return null;
    }

    public function commandFile(string $command): ?string
    {
        try {
            $instance = $this->artisan->all()[$command] ?? null;
        } catch (Throwable) {
            return null;
        }

        if (! $instance instanceof Command) {
            return null;
        }

        if ($instance instanceof ClosureCommand) {
            return is_file($this->root.'/routes/console.php') ? 'routes/console.php' : null;
        }

        $file = (new ReflectionClass($instance))->getFileName();

        return is_string($file) ? $this->project($file) : null;
    }

    public function routeFile(string $name): ?string
    {
        $route = $this->router->getRoutes()->getByName($name);
        $controller = $route?->getAction('controller');

        if (! is_string($controller)) {
            return null;
        }

        return $this->classFile(explode('@', $controller)[0]);
    }

    private function project(string $file): ?string
    {
        $real = realpath($file) ?: $file;
        $root = rtrim(realpath($this->root) ?: $this->root, '/');

        if (! str_starts_with($real, $root.'/')) {
            return null;
        }

        $relative = substr($real, strlen($root) + 1);

        return str_starts_with($relative, 'vendor/') ? null : $relative;
    }
}
