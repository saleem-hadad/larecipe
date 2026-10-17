<?php

namespace LaRecipe\Domains\References\Services;

/**
 * The words both renderers use for each kind of reference, so HTML and agent
 * Markdown say the same thing, including when a reference no longer resolves.
 */
final class ReferenceLabels
{
    public static function kind(string $kind): string
    {
        return match ($kind) {
            'route' => 'Route',
            'artisan' => 'Command',
            'model' => 'Model',
            'event' => 'Event',
            default => ucfirst($kind),
        };
    }

    /** The reader-facing notice for a reference that resolved to nothing. Code spans are marked with backticks. */
    public static function missing(string $kind, string $target): string
    {
        $target = '`'.str_replace('`', '', $target).'`';

        return match ($kind) {
            'route' => "The route {$target} is not defined in this app.",
            'artisan' => "The command {$target} is not registered in this app.",
            'model' => "The model {$target} was not found in the namespaces these docs may describe.",
            'event' => "The event {$target} was not found in this app.",
            default => "The {$kind} reference {$target} did not resolve.",
        };
    }
}
