<?php

namespace LaRecipe\Domains\Markdown\Services;

use LaRecipe\Domains\Markdown\Components\BuiltIn\Callout;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Media;
use LaRecipe\Domains\Markdown\Components\BuiltInComponents;
use LaRecipe\Domains\Markdown\DTOs\CodeFence;
use LaRecipe\Domains\Markdown\DTOs\Snippet;
use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use LaRecipe\Domains\Markdown\Parser\SnippetInclude;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Util\RegexHelper;

/**
 * The transform stage between parsing and every renderer: it turns the
 * portable spellings into components and resolves what the page points at, so
 * HTML, agent Markdown and search records all read one finished AST.
 *
 *  - GitHub alerts (`> [!WARNING]`) become Callout components.
 *  - ```mermaid fences become Mermaid components.
 *  - Snippet includes become code blocks with the real code (or a visible notice).
 *  - HTML comments (`<!-- ... -->`) are removed: they are notes for authors.
 *  - Component URL props resolve like Markdown links; unsafe schemes are dropped.
 *  - Islands get the derived props they need (tab labels, video embeds, sources).
 *  - Video placeholders are reported, so a missing demo is never forgotten.
 */
final class DocumentTransformer
{
    public function __construct(
        private readonly RenderState $state,
        private readonly SnippetResolver $snippets,
    ) {}

    public function __invoke(DocumentParsedEvent $event): void
    {
        $document = $event->getDocument();

        foreach ($this->nodes($document) as $node) {
            match (true) {
                $node instanceof BlockQuote => $this->alert($node),
                $node instanceof FencedCode => $this->mermaid($node),
                $node instanceof SnippetInclude => $this->include($node),
                $node instanceof HtmlBlock, $node instanceof HtmlInline => $this->withoutComments($node),
                default => null,
            };
        }

        foreach ($this->nodes($document) as $node) {
            if ($node instanceof ComponentNode) {
                $this->component($node);
            }
        }
    }

    /** @return list<Node> collected first, because the transforms change the tree */
    private function nodes(Node $root): array
    {
        return iterator_to_array($root->iterator(), false);
    }

    /** HTML comments are notes for authors: removed here, they reach no renderer. Other raw HTML is left (and escaped). */
    private function withoutComments(HtmlBlock|HtmlInline $node): void
    {
        $literal = (string) preg_replace('/<!--.*?-->/s', '', $node->getLiteral(), -1, $removed);

        if ($removed === 0) {
            return;
        }

        if (trim($literal) === '') {
            $node->detach();

            return;
        }

        $node->setLiteral($literal);
    }

    private function alert(BlockQuote $quote): void
    {
        $paragraph = $quote->firstChild();

        if (! $paragraph instanceof Paragraph) {
            return;
        }

        $marker = '';
        $consumed = [];

        foreach ($paragraph->children() as $child) {
            if ($child instanceof Newline) {
                $consumed[] = $child;

                break;
            }

            if (! $child instanceof Text) {
                return;
            }

            $marker .= $child->getLiteral();
            $consumed[] = $child;
        }

        if (! preg_match('/^\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(.*)$/i', trim($marker), $matches)) {
            return;
        }

        foreach ($consumed as $node) {
            $node->detach();
        }

        if (! $paragraph->hasChildren()) {
            $paragraph->detach();
        }

        $props = ['type' => Callout::ALERTS[strtoupper($matches[1])]];

        if (trim($matches[2]) !== '') {
            $props['title'] = trim($matches[2]);
        }

        $callout = new ComponentNode('Callout', $props, syntax: 'alert');
        $callout->setStartLine($quote->getStartLine());

        // children() is a snapshot, so moving nodes while looping is safe.
        foreach ($quote->children() as $child) {
            $callout->appendChild($child);
        }

        $quote->replaceWith($callout);
    }

    private function mermaid(FencedCode $code): void
    {
        if (CodeFence::fromInfo($code->getInfo())->language !== 'mermaid') {
            return;
        }

        $diagram = new ComponentNode('Mermaid', [], syntax: 'fence');
        $diagram->literal = rtrim($code->getLiteral(), "\n");
        $diagram->setStartLine($code->getStartLine());

        $code->replaceWith($diagram);
    }

    private function include(SnippetInclude $include): void
    {
        $snippet = $this->snippets->resolve($this->snippets->root($this->state), $include->path, $include->region);

        if (! $snippet->found()) {
            $this->notice($include, $snippet);

            return;
        }

        $this->state->dependsOn((string) $snippet->file, (string) $snippet->hash);

        $fence = CodeFence::fromInfo($include->meta);
        $title = $fence->title ?? $include->path;
        $code = new FencedCode(3, '`', 0);
        $code->setInfo(trim($snippet->language.' title="'.str_replace('"', "'", $title).'" '.$this->meta($include->meta)));
        $code->setLiteral($snippet->code."\n");
        $code->setStartLine($include->getStartLine());

        $include->replaceWith($code);
    }

    /** The include's meta without its title, which is set explicitly. */
    private function meta(string $meta): string
    {
        return trim((string) preg_replace('/\btitle=(?:"[^"]*"|\'[^\']*\'|\S+)|\[[^\]]+\]/', '', $meta));
    }

    /** A missing sample is shown to the reader and reported, never silently dropped. */
    private function notice(SnippetInclude $include, Snippet $snippet): void
    {
        [$rule, $reason] = match ($snippet->status) {
            Snippet::MISSING_REGION => ['snippets.missing', "the region \"{$include->region}\" does not exist in that file."],
            Snippet::DENIED => ['snippets.denied', 'the path is outside the snippet root, or the file may hold secrets.'],
            Snippet::DISABLED => ['snippets.disabled', 'snippet includes are not enabled for this doc set.'],
            default => ['snippets.missing', 'the file does not exist.'],
        };

        $this->state->issue($rule, "Snippet {$include->target()} could not be included: {$reason}", RenderState::ERROR, null, $include->getStartLine());

        $paragraph = new Paragraph;
        $paragraph->appendChild(new Code($include->target()));
        $paragraph->appendChild(new Text(' could not be included: '.$reason));

        $callout = new ComponentNode('Callout', ['type' => 'warning', 'title' => 'Snippet not found'], syntax: 'include');
        $callout->setStartLine($include->getStartLine());
        $callout->appendChild($paragraph);

        $include->replaceWith($callout);
    }

    private function component(ComponentNode $node): void
    {
        foreach (BuiltInComponents::URL_PROPS as $prop) {
            if (isset($node->props[$prop]) && is_string($node->props[$prop])) {
                $node->props[$prop] = $this->url($prop, $node->props[$prop]);
            }
        }

        if ($node->isRaw()) {
            $node->props['source'] ??= $node->literal;
        }

        match ($node->name) {
            'Tabs' => $node->props['labels'] = $this->childProps($node, 'Tab', 'label'),
            'CodeGroup' => $node->props['labels'] = $this->codeLabels($node),
            'Video' => $this->video($node),
            default => null,
        };
    }

    /** Resolves a URL prop like a Markdown link: relative .md links and assets become reader URLs. */
    private function url(string $prop, string $url): string
    {
        $url = trim($url);

        if ($this->state->ref !== null) {
            $url = str_replace(['{{version}}', '%7B%7Bversion%7D%7D'], $this->state->ref->version, $url);
        }

        if ($url === '' || RegexHelper::isLinkPotentiallyUnsafe($url)) {
            return '';
        }

        if ($this->state->ref === null || $this->state->links === null || str_starts_with($url, '#') || preg_match('#^([a-z][a-z0-9+.-]*:|//)#i', $url)) {
            return $url;
        }

        $resolved = $prop === 'href'
            ? $this->state->links->page($this->state->ref, $url)
            : $this->state->links->asset($this->state->ref, $url);

        return $resolved ?? $url;
    }

    /** @return list<string> */
    private function childProps(ComponentNode $node, string $child, string $prop): array
    {
        $values = [];

        foreach ($node->children() as $candidate) {
            if ($candidate instanceof ComponentNode && $candidate->name === $child) {
                $values[] = is_scalar($candidate->props[$prop] ?? null) ? (string) $candidate->props[$prop] : '';
            }
        }

        return $values;
    }

    /** @return list<string> each code block's title, or its language */
    private function codeLabels(ComponentNode $group): array
    {
        $labels = [];

        foreach ($group->children() as $child) {
            if ($child instanceof FencedCode) {
                $labels[] = CodeFence::fromInfo($child->getInfo())->label();
            }
        }

        return $labels;
    }

    private function video(ComponentNode $node): void
    {
        // The embed URL reaches an iframe, so only LaRecipe derives it (YouTube or Vimeo below).
        unset($node->props['embed'], $node->props['provider']);
        $src = is_string($node->props['src'] ?? null) ? $node->props['src'] : '';

        if ($src === '') {
            $label = is_string($node->props['placeholder'] ?? null) ? $node->props['placeholder'] : Media::PLACEHOLDER;
            $this->state->issue('media.placeholder', "Video placeholder \"{$label}\" has no src yet.", RenderState::WARNING, 'Video', $node->getStartLine());

            return;
        }

        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $src, $matches)) {
            $node->props['embed'] = 'https://www.youtube-nocookie.com/embed/'.$matches[1];
            $node->props['provider'] = 'youtube';
        } elseif (preg_match('~^https?://(?:www\.)?(?:player\.)?vimeo\.com/(?:video/)?(\d+)~', $src, $matches)) {
            $node->props['embed'] = 'https://player.vimeo.com/video/'.$matches[1];
            $node->props['provider'] = 'vimeo';
        }
    }
}
