<?php

namespace LaRecipe\Domains\Markdown\Services;

use Closure;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;
use LaRecipe\Domains\Markdown\Parser\ComponentNode;
use LaRecipe\Domains\Markdown\Parser\ComponentRenderer;
use LaRecipe\Domains\Markdown\Parser\ComponentStartParser;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

/**
 * Builds the one commonmark environment every LaRecipe page is parsed with.
 *
 * Raw HTML is escaped. Component tags are recognized by our own block parser at
 * priority 45, ahead of CommonMark's HTML block parser (40), so they work
 * without ever allowing raw HTML.
 */
final class MarkdownEnvironmentFactory
{
    /** @var list<Closure(EnvironmentBuilderInterface, RenderState): void> */
    private array $extenders = [];

    public function __construct(
        private readonly ComponentRegistry $components,
        private readonly array $config = [],
    ) {}

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

        $environment->addBlockStartParser(new ComponentStartParser, 45);
        $environment->addRenderer(ComponentNode::class, new ComponentRenderer($this->components, $state));
        $environment->addEventListener(DocumentParsedEvent::class, new DocumentProcessor($state), -50);

        foreach ($this->extenders as $extender) {
            $extender($environment, $state);
        }

        return $environment;
    }

    /** Part of the render cache key: changes when extensions or config change. */
    public function version(): string
    {
        return substr(hash('xxh128', json_encode($this->config).count($this->extenders).$this->components->version()), 0, 12);
    }
}
