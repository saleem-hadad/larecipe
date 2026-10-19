<?php

namespace LaRecipe\Domains\Markdown\Components;

use Closure;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Accordions;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Callout;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Cards;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Inline;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Media;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Steps;
use LaRecipe\Domains\Markdown\Components\BuiltIn\Tabs;

/**
 * The components every LaRecipe site has. Each one renders semantic HTML that
 * works without JavaScript and degrades to portable Markdown for agents.
 * COMPONENTS.md next to this file is the HTML contract the UI styles.
 */
final class BuiltInComponents
{
    public const CALLOUT_TYPES = Callout::TYPES;

    /** Props that hold URLs: resolved like Markdown links, made absolute for agents, unsafe schemes dropped. */
    public const URL_PROPS = ['href', 'src', 'poster'];

    /** @param (Closure(string, ?string): ?string)|null $strings a UI string by key ("video.placeholder"), in a page's language */
    public static function register(ComponentRegistry $registry, ?Closure $strings = null): void
    {
        $definitions = [
            ...Callout::definitions(),
            ...Steps::definitions(),
            ...Tabs::definitions(),
            ...Cards::definitions(),
            ...Accordions::definitions(),
            ...Inline::definitions(),
            ...Media::definitions($strings),
        ];

        foreach ($definitions as $definition) {
            $registry->register($definition);
        }
    }

    /** A GitHub alert: portable on GitHub and readable by every agent. */
    public static function alert(string $type, string $children, ?string $title = null): string
    {
        return Callout::alert($type, $children, $title);
    }
}
