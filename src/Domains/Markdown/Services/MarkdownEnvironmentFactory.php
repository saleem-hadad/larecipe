<?php

namespace LaRecipe\Domains\Markdown\Services;

use Closure;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Enums\HtmlInput;
use LaRecipe\Domains\Markdown\Parser\CodeBlockRenderer;
use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use LaRecipe\Domains\Markdown\Parser\ComponentRenderer;
use LaRecipe\Domains\Markdown\Parser\ComponentStartParser;
use LaRecipe\Domains\Markdown\Parser\DirectiveStartParser;
use LaRecipe\Domains\Markdown\Parser\MathStartParser;
use LaRecipe\Domains\Markdown\Parser\RawHtmlRenderer;
use LaRecipe\Domains\Markdown\Parser\SnippetIncludeStartParser;
use LaRecipe\Domains\Markdown\Support\PassTheme;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlRenderer;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

/**
 * Builds the one commonmark environment every LaRecipe page is parsed with.
 *
 * Raw HTML is escaped, unless a repository set opts into html_input: allow
 * (RawHtmlRenderer decides per page). Component tags are recognized by our own
 * block parser at priority 45, ahead of CommonMark's HTML block parser (40), so
 * they work without ever allowing raw HTML. Directives (`:::`) start at 80, display math
 * (`$$`) at 55 and snippet includes (`<<<`) at 47.
 *
 * After parsing, the document transformer (-40) turns alerts, mermaid fences and
 * includes into their final nodes, then the document processor (-50) assigns
 * heading ids and resolves links. Every renderer reads the result.
 */
final class MarkdownEnvironmentFactory
{
    /** @var list<Closure(EnvironmentBuilderInterface, RenderState): void> */
    private array $extenders = [];

    /** @param (Closure(string): HtmlInput)|null $htmlInputFor how a doc set treats raw HTML; escaped when null */
    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly array $config = [],
        private readonly CodeHighlighter $highlighter = new CodeHighlighter,
        private readonly SnippetResolver $snippets = new SnippetResolver,
        private readonly ?Closure $htmlInputFor = null,
    ) {}

    /** What the doc set does with raw HTML: escape (the default), allow, or escape and sanitize. */
    public function htmlInput(?string $set): HtmlInput
    {
        return $set !== null && $this->htmlInputFor !== null ? ($this->htmlInputFor)($set) : HtmlInput::Escape;
    }

    /** @param Closure(EnvironmentBuilderInterface, RenderState): void $extender */
    public function extend(Closure $extender): void
    {
        $this->extenders[] = $extender;
    }

    public function make(RenderState $state): Environment
    {
        $environment = new Environment(array_replace_recursive([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'attributes' => ['allow' => ['id', 'class']],
        ], $this->config));

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new AttributesExtension);
        $environment->addExtension(new FootnoteExtension);
        $environment->addExtension(new DescriptionListExtension);

        $environment->addBlockStartParser(new DirectiveStartParser($this->components), 80);
        $environment->addBlockStartParser(new MathStartParser, 55);
        $environment->addBlockStartParser(new SnippetIncludeStartParser, 47);
        $environment->addBlockStartParser(new ComponentStartParser($this->components), 45);

        $environment->addRenderer(ComponentNode::class, new ComponentRenderer($this->components, $state));
        // Ahead of GFM's own raw HTML renderers (50), keeping its filter of <script>, <iframe>, <style> and the like.
        $environment->addRenderer(HtmlBlock::class, new DisallowedRawHtmlRenderer(new RawHtmlRenderer($state)), 100);
        $environment->addRenderer(HtmlInline::class, new DisallowedRawHtmlRenderer(new RawHtmlRenderer($state)), 100);
        $environment->addRenderer(FencedCode::class, new CodeBlockRenderer($this->highlighter), 10);
        $environment->addRenderer(IndentedCode::class, new CodeBlockRenderer($this->highlighter), 10);

        $environment->addEventListener(DocumentParsedEvent::class, new DocumentTransformer($state, $this->snippets), -40);
        $environment->addEventListener(DocumentParsedEvent::class, new DocumentProcessor($state), -50);

        foreach ($this->extenders as $extender) {
            $extender($environment, $state);
        }

        return $environment;
    }

    /** Part of the render cache key: changes when extensions, config, the code theme or the set's raw HTML handling change. */
    public function version(?string $set = null): string
    {
        return substr(hash('xxh128', json_encode($this->config).count($this->extenders).$this->components->version().PassTheme::VERSION.$this->htmlInput($set)->value), 0, 12);
    }
}
