<?php

namespace LaRecipe\Domains\Markdown\Services;

/**
 * Heading ids that read well in URLs in every script: Latin is lowercased and
 * dashed, Arabic and other scripts keep their letters.
 */
final class Slugger
{
    /** @var array<string, int> */
    private array $used = [];

    public function reset(): void
    {
        $this->used = [];
    }

    public function slug(string $text): string
    {
        $slug = mb_strtolower(trim($text));
        $slug = preg_replace('/[^\p{L}\p{N}\s_-]+/u', '', $slug) ?? $slug;
        $slug = preg_replace('/[\s_-]+/u', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'section';
        }

        return $this->unique($slug);
    }

    public function reserve(string $id): string
    {
        return $this->unique($id);
    }

    private function unique(string $slug): string
    {
        if (! isset($this->used[$slug])) {
            $this->used[$slug] = 0;

            return $slug;
        }

        do {
            $candidate = $slug.'-'.(++$this->used[$slug]);
        } while (isset($this->used[$candidate]));

        $this->used[$candidate] = 0;

        return $candidate;
    }
}
