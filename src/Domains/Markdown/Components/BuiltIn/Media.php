<?php

namespace LaRecipe\Domains\Markdown\Components\BuiltIn;

use LaRecipe\Domains\Markdown\Components\ComponentDefinition;
use LaRecipe\Domains\Markdown\Components\Markup;

/**
 * Captioned media, video, diagrams and math. Each one has a readable fallback
 * (the image, a plain video element or a link, the diagram source, the TeX) and
 * a React island that loads the heavy part only when the reader needs it.
 */
final class Media
{
    public const PLACEHOLDER = 'Demo video coming soon';

    /** @return list<ComponentDefinition> */
    public static function definitions(): array
    {
        return [
            new ComponentDefinition(
                name: 'Frame',
                props: [
                    'caption' => ['type' => 'string', 'description' => 'Shown under the media'],
                ],
                fallback: function (array $props, string $children) {
                    $caption = Markup::string($props, 'caption');

                    return '<figure class="lr-frame">'.$children.($caption !== '' ? '<figcaption>'.Markup::e($caption).'</figcaption>' : '').'</figure>';
                },
                markdown: function (array $props, string $children) {
                    $caption = Markup::string($props, 'caption');

                    return $children.($caption !== '' ? "\n\n*{$caption}*" : '');
                },
                island: true,
                description: 'Captioned, zoomable media.',
            ),
            new ComponentDefinition(
                name: 'Video',
                props: [
                    'src' => ['type' => 'string', 'description' => 'A video file, or a YouTube or Vimeo URL'],
                    'poster' => ['type' => 'string', 'description' => 'Image shown before the video plays'],
                    'title' => ['type' => 'string', 'description' => 'What the video shows'],
                    'duration' => ['type' => 'string', 'description' => 'Length, for example 2:40'],
                    'placeholder' => ['type' => 'string', 'description' => 'Label for the frame shown until src is set'],
                ],
                fallback: fn (array $props) => self::video($props),
                markdown: function (array $props) {
                    $src = Markup::string($props, 'src');
                    $label = Markup::caption(Markup::string($props, 'title'), Markup::string($props, 'duration'));

                    if ($src === '') {
                        $planned = Markup::string($props, 'placeholder') ?: Markup::string($props, 'title') ?: 'demo';

                        return "*Video coming soon: {$planned}*";
                    }

                    return '[Video: '.($label !== '' ? $label : 'watch').']('.$src.')';
                },
                island: true,
                description: 'A video that loads only when the reader asks. Without src it shows a labelled placeholder.',
            ),
            new ComponentDefinition(
                name: 'Mermaid',
                fallback: fn (array $props, string $children) => '<pre class="lr-mermaid"><code>'.$children.'</code></pre>',
                markdown: fn (array $props, string $children) => "```mermaid\n".rtrim($children)."\n```",
                island: true,
                description: 'A Mermaid diagram. A ```mermaid code block works too.',
                raw: true,
            ),
            new ComponentDefinition(
                name: 'Math',
                props: [
                    'display' => ['type' => 'string', 'default' => 'block', 'enum' => ['block', 'inline'], 'description' => 'block or inline'],
                ],
                fallback: fn (array $props, string $children) => sprintf(
                    '<div class="lr-math" data-display="%s"><code>%s</code></div>',
                    Markup::e($props['display']),
                    $children,
                ),
                markdown: fn (array $props, string $children) => "\$\$\n".trim($children)."\n\$\$",
                island: true,
                description: 'A TeX formula. A $$ ... $$ block works too.',
                raw: true,
            ),
        ];
    }

    private static function video(array $props): string
    {
        $src = Markup::string($props, 'src');
        $poster = Markup::string($props, 'poster');
        $caption = Markup::caption(Markup::string($props, 'title'), Markup::string($props, 'duration'));
        $figcaption = $caption !== '' ? '<figcaption>'.Markup::e($caption).'</figcaption>' : '';

        if ($src === '') {
            $label = Markup::string($props, 'placeholder') ?: self::PLACEHOLDER;

            return '<figure class="lr-video lr-video-placeholder"><div class="lr-video-frame"><span>'.Markup::e($label).'</span></div>'.$figcaption.'</figure>';
        }

        if (Markup::string($props, 'embed') !== '') {
            $provider = Markup::string($props, 'provider') === 'vimeo' ? 'Vimeo' : 'YouTube';

            return '<figure class="lr-video"><a class="lr-video-link" href="'.Markup::e($src).'">'
                .($poster !== '' ? '<img src="'.Markup::e($poster).'" alt="" loading="lazy">' : '')
                .'<span class="lr-video-link-label">Watch on '.$provider.'</span></a>'.$figcaption.'</figure>';
        }

        return '<figure class="lr-video"><video controls preload="none" src="'.Markup::e($src).'"'
            .($poster !== '' ? ' poster="'.Markup::e($poster).'"' : '').'></video>'.$figcaption.'</figure>';
    }
}
