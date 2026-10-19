<?php

namespace LaRecipe\Domains\Markdown\Services;

use LaRecipe\Domains\Markdown\DTOs\TocItem;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Block\AbstractBlock;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;

/**
 * Runs once per parsed page: gives every heading a stable id, collects the table
 * of contents, and rewrites relative links and images to reader URLs.
 */
final class DocumentProcessor
{
    public function __construct(
        private readonly RenderState $state,
        private readonly Slugger $slugger = new Slugger,
        private readonly array $tocLevels = [2, 3],
    ) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        $this->slugger->reset();

        foreach ($event->getDocument()->iterator() as $node) {
            if ($node instanceof Heading) {
                $this->heading($node);
            } elseif ($node instanceof Image) {
                $this->image($node);
            } elseif ($node instanceof Link) {
                $this->link($node);
            }
        }
    }

    private function heading(Heading $heading): void
    {
        $text = trim($this->text($heading));
        $explicit = $heading->data->get('attributes/id', null);
        $id = is_string($explicit) && $explicit !== '' ? $this->slugger->reserve($explicit) : $this->slugger->slug($text);

        $heading->data->set('attributes/id', $id);

        if ($heading->getLevel() === 1 && $this->state->heading === null) {
            $this->state->heading = $text;
        }

        if (in_array($heading->getLevel(), $this->tocLevels, true)) {
            $this->state->toc[] = new TocItem($heading->getLevel(), $id, $text);
        }
    }

    private function link(Link $link): void
    {
        $url = $this->placeholders($link->getUrl());
        $link->setUrl($url);

        if ($this->state->ref === null || $this->state->links === null || $this->isExternal($url)) {
            return;
        }

        $resolved = $this->state->links->page($this->state->ref, $url);

        if ($resolved !== null) {
            $this->state->internalLinks[] = ['href' => $url, 'line' => $this->lineOf($link)];
            $link->setUrl($resolved);
        }
    }

    private function image(Image $image): void
    {
        $url = $this->placeholders($image->getUrl());
        $image->setUrl($url);

        if ($this->state->ref === null || $this->state->links === null || $this->isExternal($url)) {
            return;
        }

        $resolved = $this->state->links->asset($this->state->ref, $url);

        if ($resolved !== null) {
            $image->setUrl($resolved);
        }
    }

    /** {{version}} keeps working for content written for v2. */
    private function placeholders(string $url): string
    {
        if ($this->state->ref === null) {
            return $url;
        }

        return str_replace(['{{version}}', '%7B%7Bversion%7D%7D'], $this->state->ref->version, $url);
    }

    private function isExternal(string $url): bool
    {
        return $url === '' || str_starts_with($url, '#') || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url) === 1;
    }

    private function lineOf(Node $node): ?int
    {
        for ($parent = $node->parent(); $parent !== null; $parent = $parent->parent()) {
            if ($parent instanceof AbstractBlock) {
                return $parent->getStartLine();
            }
        }

        return null;
    }

    private function text(Node $node): string
    {
        $text = '';

        foreach ($node->children() as $child) {
            $text .= match (true) {
                $child instanceof Text, $child instanceof Code => $child->getLiteral(),
                default => $this->text($child),
            };
        }

        return $text;
    }
}
