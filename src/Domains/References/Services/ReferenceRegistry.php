<?php

namespace LaRecipe\Domains\References\Services;

use LaRecipe\Domains\References\Contracts\ReferenceResolver;

/**
 * The resolvers the app has, by kind. Holds no request state, so one instance
 * serves every request (Octane included).
 */
final class ReferenceRegistry
{
    /** @var array<string, ReferenceResolver> */
    private array $resolvers = [];

    /** @param iterable<ReferenceResolver> $resolvers */
    public function __construct(iterable $resolvers = [])
    {
        foreach ($resolvers as $resolver) {
            $this->add($resolver);
        }
    }

    public function add(ReferenceResolver $resolver): void
    {
        $this->resolvers[$resolver->kind()] = $resolver;
    }

    public function get(string $kind): ?ReferenceResolver
    {
        return $this->resolvers[$kind] ?? null;
    }

    /** @return array<string, ReferenceResolver> */
    public function all(): array
    {
        return $this->resolvers;
    }
}
