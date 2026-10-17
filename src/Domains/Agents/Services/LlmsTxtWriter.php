<?php

namespace LaRecipe\Domains\Agents\Services;

use LaRecipe\Domains\Agents\DTOs\IndexEntry;
use LaRecipe\Domains\Agents\DTOs\OutlineSection;

/**
 * Writes the llms.txt format: an H1, a one-line blockquote summary, optional
 * paragraphs, then "## Heading" sections that each hold a list of
 * "- [Title](url): description" lines.
 *
 * Nested sections are written as their own H2 ("Billing / Webhooks"), because
 * the format has file lists under H2 headings only.
 */
final class LlmsTxtWriter
{
    /**
     * @param  list<string>  $details  paragraphs between the summary and the first section
     * @param  list<OutlineSection>  $sections
     */
    public function write(string $title, string $summary, array $details, array $sections): string
    {
        $lines = ['# '.$this->line($title), '', '> '.$this->line($summary), ''];

        foreach ($details as $paragraph) {
            if (trim($paragraph) !== '') {
                $lines[] = trim($paragraph);
                $lines[] = '';
            }
        }

        foreach ($sections as $section) {
            $this->section($lines, $section, null);
        }

        return rtrim(implode("\n", $lines))."\n";
    }

    public function entry(IndexEntry $entry): string
    {
        $text = str_replace(['[', ']'], ['\\[', '\\]'], $this->line($entry->title));
        $line = '- ['.$text.']('.str_replace([' ', ')'], ['%20', '%29'], $entry->url).')';

        return $entry->description !== null && trim($entry->description) !== ''
            ? $line.': '.$this->line($entry->description)
            : $line;
    }

    /** @param list<string> $lines */
    private function section(array &$lines, OutlineSection $section, ?string $parent): void
    {
        $heading = $parent === null ? $section->heading : $parent.' / '.$section->heading;

        if ($section->entries !== []) {
            $lines[] = '## '.$this->line($heading);
            $lines[] = '';

            foreach ($section->entries as $entry) {
                $lines[] = $this->entry($entry);
            }

            $lines[] = '';
        }

        foreach ($section->children as $child) {
            $this->section($lines, $child, $heading);
        }
    }

    /** One line of text: newlines and runs of whitespace collapse to a single space. */
    private function line(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
