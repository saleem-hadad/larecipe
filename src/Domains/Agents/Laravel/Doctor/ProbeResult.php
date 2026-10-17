<?php

namespace LaRecipe\Domains\Agents\Laravel\Doctor;

/**
 * One response the doctor received.
 */
final readonly class ProbeResult
{
    /** @param array<string, list<string|null>> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {}

    public function header(string $name): string
    {
        return (string) ($this->headers[strtolower($name)][0] ?? '');
    }

    public function isMarkdown(): bool
    {
        return $this->status === 200 && str_starts_with($this->header('Content-Type'), 'text/markdown');
    }

    public function isRedirect(): bool
    {
        return $this->status >= 300 && $this->status < 400 && $this->header('Location') !== '';
    }
}
