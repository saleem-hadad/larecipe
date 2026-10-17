<?php

namespace LaRecipe\Domains\Markdown\DTOs;

/**
 * The outcome of resolving one snippet include: the code, or why there is none.
 */
final readonly class Snippet
{
    public const FOUND = 'found';

    public const MISSING_FILE = 'missing_file';

    public const MISSING_REGION = 'missing_region';

    /** Outside the snippet root, or a file that may hold secrets (.env, keys, storage/). */
    public const DENIED = 'denied';

    /** The doc set has no snippet root (database sets, unless configured). */
    public const DISABLED = 'disabled';

    public function __construct(
        public string $status,
        public string $code = '',
        public ?string $file = null,
        public ?string $hash = null,
        public ?string $language = null,
    ) {}

    public function found(): bool
    {
        return $this->status === self::FOUND;
    }
}
