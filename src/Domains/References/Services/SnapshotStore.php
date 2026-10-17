<?php

namespace LaRecipe\Domains\References\Services;

use LaRecipe\Domains\References\DTOs\Reference;

/**
 * The references snapshot (bootstrap/cache/larecipe/references.json) that
 * larecipe:references:snapshot writes, so CI without a database, and the future
 * standalone CLI, can still resolve references.
 */
final class SnapshotStore
{
    private ?array $data = null;

    public function __construct(private readonly string $path) {}

    public function path(): string
    {
        return $this->path;
    }

    public function exists(): bool
    {
        return is_file($this->path);
    }

    /** A target as written, or a class by its short name ("Order" finds "App\Models\Order"). */
    public function find(string $kind, string $target): ?Reference
    {
        $entries = (array) ($this->load()['references'][$kind] ?? []);
        $target = ltrim($target, '\\');
        $entry = $entries[$target] ?? null;

        if ($entry === null && in_array($kind, ['model', 'event'], true)) {
            foreach ($entries as $key => $candidate) {
                if (str_ends_with((string) $key, '\\'.$target)) {
                    $entry = $candidate;

                    break;
                }
            }
        }

        return is_array($entry) ? Reference::fromArray($entry) : null;
    }

    /** @return list<string> */
    public function targets(string $kind): array
    {
        return array_values(array_map('strval', array_keys((array) ($this->load()['references'][$kind] ?? []))));
    }

    /** @return array<string, Reference> every snapshotted reference of one kind, by target */
    public function entries(string $kind): array
    {
        $entries = [];

        foreach ((array) ($this->load()['references'][$kind] ?? []) as $target => $data) {
            if (is_array($data)) {
                $entries[(string) $target] = Reference::fromArray($data);
            }
        }

        return $entries;
    }

    public function fingerprint(): string
    {
        return $this->exists() ? (string) hash_file('xxh128', $this->path) : 'none';
    }

    /** @param array<string, array<string, Reference>> $references kind => target => reference */
    public function write(array $references, string $generatedAt): void
    {
        $payload = ['generated_at' => $generatedAt, 'references' => []];

        foreach ($references as $kind => $byTarget) {
            ksort($byTarget);
            $payload['references'][$kind] = array_map(fn (Reference $reference) => $reference->toArray(), $byTarget);
        }

        if (! is_dir(dirname($this->path))) {
            mkdir(dirname($this->path), 0755, true);
        }

        file_put_contents($this->path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
        $this->data = null;
    }

    private function load(): array
    {
        if ($this->data === null) {
            $decoded = $this->exists() ? json_decode((string) file_get_contents($this->path), true) : null;
            $this->data = is_array($decoded) ? $decoded : [];
        }

        return $this->data;
    }
}
