<?php

namespace LaRecipe\Domains\Agents\DTOs;

/**
 * One agent-readiness check: what was checked, how it went and what to do.
 */
final readonly class DoctorCheck
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    public const SKIP = 'skip';

    public function __construct(
        public string $id,
        public string $title,
        public string $status,
        public string $message,
        public ?string $fix = null,
    ) {}

    public static function pass(string $id, string $title, string $message): self
    {
        return new self($id, $title, self::PASS, $message);
    }

    public static function warn(string $id, string $title, string $message, ?string $fix = null): self
    {
        return new self($id, $title, self::WARN, $message, $fix);
    }

    public static function fail(string $id, string $title, string $message, ?string $fix = null): self
    {
        return new self($id, $title, self::FAIL, $message, $fix);
    }

    public static function skip(string $id, string $title, string $message): self
    {
        return new self($id, $title, self::SKIP, $message);
    }

    public function toArray(): array
    {
        return array_filter([
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'message' => $this->message,
            'fix' => $this->fix,
        ], fn ($value) => $value !== null);
    }
}
