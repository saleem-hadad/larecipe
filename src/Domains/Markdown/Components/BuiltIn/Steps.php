<?php

namespace LaRecipe\Domains\Markdown\Components\BuiltIn;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\Markup;

/**
 * A numbered procedure. Each <Step> renders as one list item, so the steps are
 * an ordered list in every representation: HTML without JavaScript, and a
 * numbered Markdown list for agents.
 */
final class Steps
{
    /** @return list<ComponentDefinition> */
    public static function definitions(): array
    {
        return [
            new ComponentDefinition(
                name: 'Steps',
                fallback: fn (array $props, string $children) => '<ol class="lr-steps">'.$children.'</ol>',
                markdown: fn (array $props, string $children) => self::number($children),
                description: 'A numbered procedure. Put one <Step title="..."> inside for each step.',
            ),
            new ComponentDefinition(
                name: 'Step',
                props: [
                    'title' => ['type' => 'string', 'required' => true, 'description' => 'What the reader does in this step'],
                ],
                fallback: fn (array $props, string $children) => sprintf(
                    '<li class="lr-step"><div class="lr-step-title">%s</div><div class="lr-step-body">%s</div></li>',
                    Markup::e($props['title'] ?? ''),
                    $children,
                ),
                markdown: fn (array $props, string $children) => '1. **'.Markup::string($props, 'title').'**'
                    .($children !== '' ? "\n\n".Markup::indent($children, '   ') : ''),
                description: 'One step inside <Steps>.',
            ),
        ];
    }

    /**
     * Every step writes itself as "1." (a step cannot know its position); the
     * list renumbers them. Step bodies are indented, so only step lines match.
     */
    private static function number(string $children): string
    {
        $position = 0;

        return (string) preg_replace_callback('/^1\. /m', function () use (&$position) {
            return (++$position).'. ';
        }, $children);
    }
}
