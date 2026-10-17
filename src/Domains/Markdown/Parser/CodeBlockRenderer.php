<?php

namespace LaRecipe\Domains\Markdown\Parser;

use LaRecipe\Domains\Markdown\DTOs\CodeFence;
use LaRecipe\Domains\Markdown\Services\CodeHighlighter;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\Xml;

/**
 * Every code block renders as one figure: a header with the title (or the
 * language) and the highlighted code, one span per line. The UI adds the copy
 * button and the tabs of a CodeGroup; this markup is the whole contract
 * (Components/COMPONENTS.md).
 *
 *   <figure class="lr-code" data-language="php" data-title="app/X.php">
 *     <figcaption class="lr-code-header"><span class="lr-code-title">app/X.php</span></figcaption>
 *     <pre class="lr-pre" tabindex="0"><code><span class="lr-line" data-line="1">...</span></code></pre>
 *   </figure>
 */
final class CodeBlockRenderer implements NodeRendererInterface
{
    public function __construct(private readonly CodeHighlighter $highlighter) {}

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        assert($node instanceof FencedCode || $node instanceof IndentedCode);

        $fence = $node instanceof FencedCode ? CodeFence::fromInfo($node->getInfo()) : new CodeFence;

        $figure = ' data-language="'.Xml::escape($fence->language ?? 'text').'"'
            .($fence->title !== null ? ' data-title="'.Xml::escape($fence->title).'"' : '');

        $pre = ' tabindex="0"'
            .($fence->lineNumbers ? ' data-line-numbers' : '')
            .($fence->marksChanges() ? ' data-diff' : '');

        return '<figure class="lr-code"'.$figure.'>'
            .'<figcaption class="lr-code-header"><span class="lr-code-title">'.Xml::escape($fence->label()).'</span></figcaption>'
            .'<pre class="lr-pre"'.$pre.'><code>'.$this->highlighter->highlight($node->getLiteral(), $fence).'</code></pre>'
            .'</figure>';
    }
}
