<?php

namespace LaRecipe\Domains\Sync\DTOs;

/**
 * One `sources:` entry: a glob relative to the project root, optionally
 * narrowed to a named region of a file with `path#region`.
 *
 *   app/Billing/**              any file under app/Billing
 *   routes/billing.php          one file
 *   app/Billing                 a folder (same as app/Billing/**)
 *   routes/api.php#billing      only the `#region billing` part of the file
 */
final readonly class SourcePattern
{
    public function __construct(
        public string $raw,
        public string $glob,
        public ?string $region = null,
    ) {}

    public static function parse(string $source): self
    {
        $source = trim($source);
        $glob = $source;
        $region = null;
        $hash = strrpos($source, '#');

        if ($hash !== false && $hash > 0 && ! str_contains(substr($source, $hash), '/')) {
            $glob = substr($source, 0, $hash);
            $region = substr($source, $hash + 1) ?: null;
        }

        $glob = preg_replace('#^\./#', '', ltrim(str_replace('\\', '/', $glob), '/')) ?? $glob;

        return new self($source, $glob, $region);
    }

    public function hasWildcard(): bool
    {
        return strpbrk($this->glob, '*?[{') !== false;
    }
}
