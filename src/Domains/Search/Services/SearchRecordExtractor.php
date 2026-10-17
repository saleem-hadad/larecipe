<?php

namespace LaRecipe\Domains\Search\Services;

use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use LaRecipe\Domains\Search\DTOs\SearchRecord;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Splits a parsed page into search records: the intro, then one record per H2
 * and H3, and a separate record for every `<Can>` and `<Cannot>` block.
 *
 * It reads the same AST the HTML comes from, so search can never index text
 * the page does not show. Gated blocks carry the ability they require, and a
 * heading opened inside a gated block never labels text outside it, so a
 * reader without the ability cannot learn even the heading.
 */
final class SearchRecordExtractor
{
    /** Gate components, and the prefix their ability gets ("!" means "must not have"). */
    public const GATES = ['Can' => '', 'Cannot' => '!'];

    /** Records keep this much text: enough to rank and match, small enough for the client budget. */
    public const TEXT_LIMIT = 1000;

    /** Component props that are visible text (a step title, a tab label). */
    private const TEXT_PROPS = ['title', 'label', 'caption'];

    /** @var list<SearchRecord> */
    private array $records = [];

    /** @var array{heading: ?string, anchor: ?string, level: int} */
    private array $section;

    /** @var list<string> */
    private array $abilities = [];

    /** @var list<string> */
    private array $text = [];

    /** Whether the current section already has a record, so a bare heading is recorded once. */
    private bool $recorded = false;

    private Page $page;

    /** @return list<SearchRecord> */
    public function extract(Page $page, Document $document): array
    {
        $this->page = $page;
        $this->records = [];
        $this->section = ['heading' => null, 'anchor' => null, 'level' => 1];
        $this->recorded = false;
        $this->abilities = [];
        $this->text = $page->description !== null ? [$page->description] : [];

        $this->walk($document);
        $this->flush();

        if ($this->records === []) {
            $this->records[] = $this->record('', $page->description ?? '');
        }

        return $this->records;
    }

    private function walk(Node $parent): void
    {
        foreach ($parent->children() as $node) {
            if ($node instanceof Heading) {
                $this->heading($node);
            } elseif ($node instanceof ComponentNode && isset(self::GATES[$node->name])) {
                $this->gate($node);
            } elseif ($node instanceof ComponentNode) {
                $this->component($node);
            } elseif ($node instanceof FencedCode || $node instanceof IndentedCode) {
                $this->text[] = $node->getLiteral();
            } elseif ($node instanceof HtmlBlock) {
                $this->text[] = strip_tags($node->getLiteral());
            } elseif ($node instanceof Paragraph || $node->firstChild() instanceof AbstractInline) {
                // Paragraphs, table cells, description terms: blocks that hold text directly.
                $this->text[] = $this->inlineText($node);
            } else {
                $this->walk($node);
            }
        }
    }

    private function heading(Heading $heading): void
    {
        $text = trim($this->inlineText($heading));

        if ($heading->getLevel() === 1) {
            return;
        }

        if ($heading->getLevel() > 3) {
            $this->text[] = $text;

            return;
        }

        $this->flush();
        $id = $heading->data->get('attributes/id', null);
        $this->section = ['heading' => $text, 'anchor' => is_string($id) ? $id : null, 'level' => $heading->getLevel()];
        $this->recorded = false;
    }

    private function gate(ComponentNode $gate): void
    {
        $ability = is_scalar($gate->props['ability'] ?? null) ? (string) $gate->props['ability'] : '';
        $outerSection = $this->section;
        $outerAbilities = $this->abilities;

        $this->flush();
        $this->abilities = [...$outerAbilities, self::GATES[$gate->name].$ability];
        $this->walk($gate);
        $this->flush();

        // A heading opened inside the block must not label text after it.
        $this->section = $outerSection;
        $this->abilities = $outerAbilities;
        $this->recorded = true;
    }

    private function component(ComponentNode $node): void
    {
        foreach (self::TEXT_PROPS as $prop) {
            if (is_string($node->props[$prop] ?? null)) {
                $this->text[] = $node->props[$prop];
            }
        }

        if ($node->isRaw()) {
            $this->text[] = (string) $node->literal;
        } elseif ($node->inline) {
            $this->text[] = $this->inlineText($node);
        } else {
            $this->walk($node);
        }
    }

    /** Closes the current section: a record when it has text, or a heading readers can find. */
    private function flush(): void
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', implode(' ', $this->text)));
        $this->text = [];

        if ($text === '' && ($this->section['heading'] === null || $this->recorded)) {
            return;
        }

        $this->records[] = $this->record($text, null);
        $this->recorded = true;
    }

    private function record(string $text, ?string $description): SearchRecord
    {
        $id = $this->page->ref->path.($this->section['anchor'] !== null ? '#'.$this->section['anchor'] : '');
        $taken = array_map(fn (SearchRecord $record) => $record->id, $this->records);
        $unique = $id;

        for ($n = 2; in_array($unique, $taken, true); $n++) {
            $unique = $id.'~'.$n;
        }

        return new SearchRecord(
            id: $unique,
            path: $this->page->ref->path,
            title: $this->page->title,
            heading: $this->section['heading'],
            anchor: $this->section['anchor'],
            level: $this->section['level'],
            text: mb_substr($text !== '' ? $text : (string) $description, 0, self::TEXT_LIMIT),
            tags: $this->page->frontmatter->tags(),
            abilities: $this->abilities,
            description: $this->page->description,
        );
    }

    private function inlineText(Node $node): string
    {
        $text = '';

        foreach ($node->children() as $child) {
            $text .= match (true) {
                $child instanceof Text, $child instanceof Code => $child->getLiteral(),
                $child instanceof Newline => ' ',
                default => $this->inlineText($child),
            };
        }

        return $text;
    }
}
