<?php

namespace LaRecipe\Domains\Sync\DTOs;

use LaRecipe\Domains\Sync\Enums\SyncMode;

/**
 * What someone asked `larecipe:sync` to do, before anything is looked up.
 *
 * A plain value so a run can be queued (toArray and fromArray) and planned
 * again inside the worker, against the files as they are when it runs.
 */
final readonly class SyncRequest
{
    /**
     * @param  ?string  $base  the diff base; null means the default branch (PushRange)
     * @param  list<string>  $pages  page paths or files given with --page
     * @param  ?string  $remote  a production set's MCP endpoint, for --remote
     */
    public function __construct(
        public ?string $set = null,
        public ?string $base = null,
        public string $head = 'HEAD',
        public array $pages = [],
        public bool $audit = false,
        public ?string $version = null,
        public ?string $locale = null,
        public bool $dryRun = false,
        public int $maxPages = 20,
        public int $maxSteps = 30,
        public ?string $provider = null,
        public ?string $model = null,
        public bool $commit = false,
        public ?string $remote = null,
    ) {}

    public function mode(): SyncMode
    {
        return match (true) {
            $this->audit => SyncMode::Audit,
            $this->pages !== [] => SyncMode::Pages,
            default => SyncMode::Diff,
        };
    }

    public function toArray(): array
    {
        return [
            'set' => $this->set,
            'base' => $this->base,
            'head' => $this->head,
            'pages' => $this->pages,
            'audit' => $this->audit,
            'version' => $this->version,
            'locale' => $this->locale,
            'dry_run' => $this->dryRun,
            'max_pages' => $this->maxPages,
            'max_steps' => $this->maxSteps,
            'provider' => $this->provider,
            'model' => $this->model,
            'commit' => $this->commit,
            'remote' => $this->remote,
        ];
    }

    public static function fromArray(array $data): self
    {
        $string = fn (string $key) => isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '' ? $data[$key] : null;

        return new self(
            set: $string('set'),
            base: $string('base'),
            head: $string('head') ?? 'HEAD',
            pages: array_values(array_map('strval', (array) ($data['pages'] ?? []))),
            audit: (bool) ($data['audit'] ?? false),
            version: $string('version'),
            locale: $string('locale'),
            dryRun: (bool) ($data['dry_run'] ?? false),
            maxPages: max(1, (int) ($data['max_pages'] ?? 20)),
            maxSteps: max(1, (int) ($data['max_steps'] ?? 30)),
            provider: $string('provider'),
            model: $string('model'),
            commit: (bool) ($data['commit'] ?? false),
            remote: $string('remote'),
        );
    }
}
