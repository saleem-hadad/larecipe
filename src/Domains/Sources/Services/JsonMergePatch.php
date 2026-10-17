<?php

namespace LaRecipe\Domains\Sources\Services;

/**
 * JSON merge patches (RFC 7386) for larecipe.json: keys in the patch replace
 * keys in the target, null removes a key, objects merge recursively and lists
 * are replaced as a whole.
 *
 * In PHP an empty array is ambiguous; it is treated as an empty list, so a
 * patch value of [] sets the key to an empty list.
 */
final class JsonMergePatch
{
    public function apply(mixed $target, mixed $patch): mixed
    {
        if (! $this->isObject($patch)) {
            return $patch;
        }

        $result = $this->isObject($target) ? $target : [];

        foreach ($patch as $key => $value) {
            if ($value === null) {
                unset($result[$key]);

                continue;
            }

            $result[$key] = $this->apply($result[$key] ?? null, $value);
        }

        return $result;
    }

    /**
     * One patch with the effect of applying $first, then $second. Used to keep
     * one updateManifest per docs migration while a session makes several edits.
     */
    public function compose(mixed $first, mixed $second): mixed
    {
        if (! $this->isObject($second)) {
            return $second;
        }

        if (! $this->isObject($first)) {
            return $this->apply($first, $second);
        }

        $result = $first;

        foreach ($second as $key => $value) {
            $result[$key] = $value === null || ! array_key_exists($key, $first)
                ? $value
                : $this->compose($first[$key], $value);
        }

        return $result;
    }

    private function isObject(mixed $value): bool
    {
        return is_array($value) && $value !== [] && ! array_is_list($value);
    }
}
