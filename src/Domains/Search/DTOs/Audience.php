<?php

namespace LaRecipe\Domains\Search\DTOs;

/**
 * Who a client index is for: the pages a reader may see and the gate
 * abilities they hold, hashed into a short key. Readers who see the same
 * things share one index variant (every guest shares the public one), and a
 * variant never contains a record its audience may not see.
 */
final readonly class Audience
{
    /**
     * @param  list<string>  $paths  visible page paths
     * @param  list<string>  $abilities  granted abilities that some record on these pages asks about
     */
    public function __construct(
        public string $key,
        public string $fingerprint,
        public array $paths,
        public array $abilities,
    ) {}

    /** @param list<string> $paths @param list<string> $abilities */
    public static function for(string $fingerprint, array $paths, array $abilities): self
    {
        sort($paths);
        sort($abilities);

        $key = substr(hash('xxh128', implode("\n", $paths)."\0".implode("\n", $abilities)), 0, 12);

        return new self($key, $fingerprint, array_values($paths), array_values($abilities));
    }

    /** The file name of this audience's client index: {audience}.{fingerprint}.json */
    public function fileName(): string
    {
        return $this->key.'.'.$this->fingerprint.'.json';
    }
}
