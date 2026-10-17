<?php

namespace LaRecipe\Domains\Access\Blocks;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\ComponentRegistry;

/**
 * The <Can> and <Cannot> components: per-role content inside a page.
 *
 * Whether a block is shown is never decided here. GateBlockFilter removes the
 * blocks a reader may not see from the AST before any renderer runs, so the
 * definitions below only ever render content the reader is allowed to read:
 * their children, unwrapped, in HTML and in agent Markdown alike.
 */
final class GateBlocks
{
    public const CAN = 'Can';

    public const CANNOT = 'Cannot';

    public const NAMES = [self::CAN, self::CANNOT];

    public static function register(ComponentRegistry $registry): void
    {
        $props = [
            'ability' => ['type' => 'string', 'required' => true, 'description' => 'A gate or policy ability defined by the host app, e.g. view-billing'],
        ];

        $registry->register(new ComponentDefinition(
            name: self::CAN,
            props: $props,
            fallback: fn (array $props, string $children) => $children,
            markdown: fn (array $props, string $children) => rtrim($children),
            description: 'Content shown only to readers the ability allows. Removed for everyone else, on every surface.',
        ));

        $registry->register(new ComponentDefinition(
            name: self::CANNOT,
            props: $props,
            fallback: fn (array $props, string $children) => $children,
            markdown: fn (array $props, string $children) => rtrim($children),
            description: 'Content shown only to readers the ability does not allow, guests included.',
        ));
    }
}
