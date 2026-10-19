<?php

namespace LaRecipe\Domains\Admin\Support;

/**
 * The JSON merge patch (RFC 7386) that turns one larecipe.json into another.
 *
 * The settings screen edits the whole manifest, while UpdateManifestAction
 * takes a patch. Sending the difference (null for every removed key, nested
 * objects only where something inside changed) means a removed key really
 * goes away, and a key the screen never touched is never rewritten.
 */
final class MergePatch
{
    /**
     * @param  array<string, mixed>  $from
     * @param  array<string, mixed>  $to
     * @return array<string, mixed> empty when nothing changed
     */
    public static function between(array $from, array $to): array
    {
        $patch = [];

        foreach (array_keys($from) as $key) {
            if (! array_key_exists($key, $to)) {
                $patch[$key] = null;
            }
        }

        foreach ($to as $key => $value) {
            $old = $from[$key] ?? null;

            if (self::isObject($value) && self::isObject($old)) {
                $nested = self::between($old, $value);

                if ($nested !== []) {
                    $patch[$key] = $nested;
                }

                continue;
            }

            if (! array_key_exists($key, $from) || $old !== $value) {
                $patch[$key] = $value;
            }
        }

        return $patch;
    }

    private static function isObject(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }
}
