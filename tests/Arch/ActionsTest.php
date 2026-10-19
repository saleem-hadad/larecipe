<?php

use Symfony\Component\Finder\Finder;

it('gives every Action class exactly one public execute() method', function () {
    $files = Finder::create()->files()->in(dirname(__DIR__, 2).'/src/Domains')->path('/Actions/')->name('*Action.php');

    foreach ($files as $file) {
        $class = 'LaRecipe\\'.str_replace(['/', '.php'], ['\\', ''], substr($file->getRealPath(), strlen(dirname(__DIR__, 2).'/src/')));
        $methods = array_filter(
            (new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $m) => ! $m->isConstructor() && $m->getDeclaringClass()->getName() === $class,
        );

        expect(array_map(fn ($m) => $m->getName(), array_values($methods)))->toBe(['execute'], "{$class} must expose only execute()");
    }
});
