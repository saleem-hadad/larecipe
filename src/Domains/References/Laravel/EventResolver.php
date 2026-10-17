<?php

namespace LaRecipe\Domains\References\Laravel;

use Closure;
use Illuminate\Contracts\Events\Dispatcher;
use LaRecipe\Domains\References\Contracts\ListsReferenceTargets;
use LaRecipe\Domains\References\Contracts\ReferenceResolver;
use LaRecipe\Domains\References\DTOs\Reference;
use LaRecipe\Domains\References\DTOs\ReferenceTable;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * <Event name="OrderShipped" />: the event's public payload (property names and
 * types, never values) and the listeners registered for it.
 *
 * Only classes the app treats as events can be described: those in an event
 * namespace (references.events.namespaces) or with listeners registered.
 */
final class EventResolver implements ListsReferenceTargets, ReferenceResolver
{
    private const SHOULD_QUEUE = 'Illuminate\Contracts\Queue\ShouldQueue';

    private const SHOULD_BROADCAST = 'Illuminate\Contracts\Broadcasting\ShouldBroadcast';

    /** @param list<string> $namespaces where short event names are looked up ("App\Events") */
    public function __construct(
        private readonly Dispatcher $events,
        private readonly array $namespaces = [],
    ) {}

    public function kind(): string
    {
        return 'event';
    }

    public function resolve(string $target, array $props = []): ?Reference
    {
        $class = $this->classFor($target);

        if ($class === null) {
            return null;
        }

        $payload = array_map(
            fn (ReflectionProperty $property) => [$property->getName(), $this->type($property)],
            array_values(array_filter(
                (new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC),
                fn (ReflectionProperty $property) => ! $property->isStatic() && $property->getName() !== 'socket',
            )),
        );

        $listeners = array_map(fn (string $listener) => [
            $listener,
            class_exists(explode('@', $listener)[0]) && is_subclass_of(explode('@', $listener)[0], self::SHOULD_QUEUE) ? 'queued' : 'sync',
        ], $this->listeners($class));

        $facts = is_subclass_of($class, self::SHOULD_BROADCAST) ? [['Broadcasts', 'yes']] : [];

        return new Reference('event', $target, $class, null, $facts, null, [
            new ReferenceTable('Payload', ['Property', 'Type'], $payload),
            new ReferenceTable('Listeners', ['Listener', 'Runs'], $listeners),
        ]);
    }

    public function targets(): array
    {
        $classes = array_filter(array_keys($this->rawListeners()), fn ($event) => is_string($event) && class_exists($event));

        foreach ($this->namespaces as $namespace) {
            array_push($classes, ...NamespaceClasses::in($namespace));
        }

        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }

    public function fingerprint(): string
    {
        $parts = [];

        foreach ($this->targets() as $class) {
            $file = (string) (new ReflectionClass($class))->getFileName();
            $parts[] = $class.':'.(is_file($file) ? filemtime($file) : '').':'.implode(',', $this->listeners($class));
        }

        return hash('xxh128', implode("\n", $parts));
    }

    private function classFor(string $target): ?string
    {
        $target = ltrim(trim($target), '\\');
        $candidates = [$target];

        foreach ($this->namespaces as $namespace) {
            $candidates[] = trim($namespace, '\\').'\\'.$target;
        }

        foreach ($candidates as $candidate) {
            if (class_exists($candidate) && ! (new ReflectionClass($candidate))->isAbstract() && $this->isEvent($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /** Only classes the app treats as events: in an event namespace, or with listeners registered. */
    private function isEvent(string $class): bool
    {
        foreach ($this->namespaces as $namespace) {
            if (str_starts_with($class, trim($namespace, '\\').'\\')) {
                return true;
            }
        }

        return ($this->rawListeners()[$class] ?? []) !== [];
    }

    /** @return array<string, array> */
    private function rawListeners(): array
    {
        return method_exists($this->events, 'getRawListeners') ? (array) $this->events->getRawListeners() : [];
    }

    /** @return list<string> listener class names (Class@method when not handle), "Closure" for closures */
    private function listeners(string $class): array
    {
        $names = [];

        foreach ($this->rawListeners()[$class] ?? [] as $listener) {
            $names[] = match (true) {
                is_string($listener) => str_ends_with($listener, '@handle') ? substr($listener, 0, -7) : $listener,
                is_array($listener) && isset($listener[0], $listener[1]) => (is_object($listener[0]) ? $listener[0]::class : (string) $listener[0]).($listener[1] === 'handle' ? '' : '@'.$listener[1]),
                $listener instanceof Closure => 'Closure',
                is_object($listener) => $listener::class,
                default => 'listener',
            };
        }

        return $names;
    }

    private function type(ReflectionProperty $property): string
    {
        $type = $property->getType();

        if ($type === null) {
            return 'mixed';
        }

        return $type instanceof ReflectionNamedType ? ($type->allowsNull() && $type->getName() !== 'mixed' ? '?' : '').$type->getName() : (string) $type;
    }
}
