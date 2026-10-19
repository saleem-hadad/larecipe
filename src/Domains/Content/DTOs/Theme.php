<?php

namespace LaRecipe\Domains\Content\DTOs;

/**
 * The tokens a team may override in larecipe.json `theme`. Everything else in
 * the design system is structure and stays.
 */
final readonly class Theme
{
    public const DEFAULT_ACCENT = 'oklch(0.55 0.2 279)';

    public function __construct(
        public ?string $accent = null,
        public string $radius = 'sharp',
        public ?string $fontSans = null,
        public ?string $fontMono = null,
        public ?string $fontArabic = null,
        public string $mode = 'system',
    ) {}

    public static function fromArray(array $data): self
    {
        $fonts = (array) ($data['fonts'] ?? []);

        return new self(
            accent: isset($data['accent']) ? (string) $data['accent'] : null,
            radius: in_array($data['radius'] ?? null, ['sharp', 'soft'], true) ? $data['radius'] : 'sharp',
            fontSans: isset($fonts['sans']) ? (string) $fonts['sans'] : null,
            fontMono: isset($fonts['mono']) ? (string) $fonts['mono'] : null,
            fontArabic: isset($fonts['arabic']) ? (string) $fonts['arabic'] : null,
            mode: in_array($data['mode'] ?? null, ['system', 'light', 'dark'], true) ? $data['mode'] : 'system',
        );
    }

    public function toArray(): array
    {
        return [
            'accent' => $this->accent,
            'radius' => $this->radius,
            'fonts' => array_filter(['sans' => $this->fontSans, 'mono' => $this->fontMono, 'arabic' => $this->fontArabic]),
            'mode' => $this->mode,
        ];
    }
}
