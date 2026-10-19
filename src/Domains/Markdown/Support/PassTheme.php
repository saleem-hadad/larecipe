<?php

namespace LaRecipe\Domains\Markdown\Support;

/**
 * "larecipe-pass": the one syntax theme LaRecipe ships. Code blocks sit on a
 * slate slab (#344258) in both light and dark mode (DESIGN.md, Code), so a single dark
 * theme serves both and the HTML carries no per-mode CSS variables.
 *
 * Colors come from DESIGN.json's syntax tokens: keywords #B4A7FF, strings
 * #9FDDB4, comments at 72% of the code foreground, functions #F2C97D, numbers
 * and constants #8FD3FF. Bump VERSION whenever a color changes, so cached
 * highlights are rebuilt.
 */
final class PassTheme
{
    public const NAME = 'larecipe-pass';

    public const VERSION = '3';

    public const TEXT = '#E8E7EE';

    public const KEYWORD = '#B4A7FF';

    public const STRING = '#9FDDB4';

    /** rgba(232, 231, 238, 0.72) written as #RRGGBBAA: AA contrast on the slate slab. */
    public const COMMENT = '#E8E7EEB8';

    public const FUNCTION = '#F2C97D';

    public const CONSTANT = '#8FD3FF';

    /** Removed diff lines: the danger hue lightened to clear AA on the slate slab and its band. */
    public const DELETED = '#F6ADA3';

    /** @return array{name: string, type: string, colors: array<string, string>, tokenColors: list<array>} a VS Code theme */
    public static function definition(): array
    {
        return [
            'name' => self::NAME,
            'type' => 'dark',
            'colors' => [
                'editor.background' => '#17161C',
                'editor.foreground' => self::TEXT,
            ],
            'tokenColors' => [
                self::rule(['comment', 'punctuation.definition.comment', 'string.comment'], self::COMMENT),
                self::rule(['keyword', 'storage', 'storage.type', 'storage.modifier', 'variable.language', 'entity.name.tag', 'punctuation.definition.tag', 'markup.heading', 'entity.name.section'], self::KEYWORD),
                self::rule(['string', 'string.quoted', 'string.template', 'punctuation.definition.string', 'markup.inline.raw', 'markup.inserted'], self::STRING),
                self::rule(['entity.name.function', 'support.function', 'meta.function-call', 'entity.other.attribute-name'], self::FUNCTION),
                self::rule(['constant', 'constant.numeric', 'constant.language', 'constant.character', 'support.constant', 'variable.other.constant', 'entity.name.type', 'entity.name.class', 'support.class', 'support.type', 'entity.other.inherited-class', 'support.type.property-name', 'markup.underline.link'], self::CONSTANT),
                self::rule(['variable', 'variable.other', 'variable.parameter'], self::TEXT),
                self::rule(['markup.deleted'], self::DELETED),
                self::rule(['meta.diff.header', 'meta.diff.range', 'meta.diff.index'], self::COMMENT),
                ['scope' => ['markup.bold', 'markup.heading'], 'settings' => ['fontStyle' => 'bold']],
                ['scope' => ['markup.italic'], 'settings' => ['fontStyle' => 'italic']],
            ],
        ];
    }

    /** @param list<string> $scopes */
    private static function rule(array $scopes, string $color): array
    {
        return ['scope' => $scopes, 'settings' => ['foreground' => $color]];
    }
}
