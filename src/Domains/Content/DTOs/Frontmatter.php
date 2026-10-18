<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Domains\Content\Enums\Visibility;

/**
 * Page-level configuration from the YAML at the top of a page.
 *
 * Frontmatter configures one page only. Set-level settings live in larecipe.json
 * and navigation structure in navigation.json. Keys under `meta:` reach the UI
 * as page meta, so extensions can define their own. Other unknown keys are
 * kept for the server (validation, agents) and never sent to readers.
 */
final readonly class Frontmatter
{
    public const KNOWN_KEYS = [
        'title', 'description', 'order', 'icon', 'sources', 'tags', 'badge', 'since',
        'deprecated', 'hidden', 'noindex', 'visibility', 'redirect_from', 'layout',
        'inserts', 'type', 'spec', 'meta',
    ];

    /** @param array<string, mixed> $data */
    public function __construct(public array $data = []) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function title(): ?string
    {
        return isset($this->data['title']) ? (string) $this->data['title'] : null;
    }

    public function description(): ?string
    {
        return isset($this->data['description']) ? (string) $this->data['description'] : null;
    }

    public function order(): ?int
    {
        return isset($this->data['order']) && is_numeric($this->data['order']) ? (int) $this->data['order'] : null;
    }

    public function icon(): ?string
    {
        return isset($this->data['icon']) ? (string) $this->data['icon'] : null;
    }

    public function hidden(): bool
    {
        return (bool) ($this->data['hidden'] ?? false);
    }

    public function noindex(): bool
    {
        return (bool) ($this->data['noindex'] ?? false);
    }

    public function visibility(): Visibility
    {
        return Visibility::fromSetting($this->data['visibility'] ?? null);
    }

    public function layout(): string
    {
        return in_array($this->data['layout'] ?? null, ['default', 'wide', 'full'], true) ? $this->data['layout'] : 'default';
    }

    /** @return list<string> */
    public function sources(): array
    {
        return array_values(array_map('strval', (array) ($this->data['sources'] ?? [])));
    }

    /** @return list<string> */
    public function tags(): array
    {
        return array_values(array_map('strval', (array) ($this->data['tags'] ?? [])));
    }

    /** @return list<string> */
    public function redirectFrom(): array
    {
        return array_values(array_map('strval', (array) ($this->data['redirect_from'] ?? [])));
    }

    /**
     * Insert points this page opts out of. false turns off all of them.
     *
     * @return array<string, bool>|false
     */
    public function inserts(): array|false
    {
        $inserts = $this->data['inserts'] ?? [];

        if ($inserts === false) {
            return false;
        }

        return is_array($inserts) ? array_map('boolval', $inserts) : [];
    }

    /**
     * The `meta:` map, which reaches the UI as page.meta for custom
     * components. Other unknown keys never leave the server: they often hold
     * notes for the team (an owner, a ticket) that readers should not see.
     *
     * @return array<string, mixed>
     */
    public function meta(): array
    {
        $meta = $this->data['meta'] ?? null;

        return is_array($meta) && ! array_is_list($meta) ? $meta : [];
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
