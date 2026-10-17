<?php

namespace LaRecipe\Domains\Content\DTOs;

use LaRecipe\Domains\Content\Enums\Direction;

/**
 * One language of a doc set. Arabic and other RTL languages are peer renderings:
 * the direction comes from the locale, never from a theme switch.
 */
final readonly class Locale
{
    public function __construct(
        public string $code,
        public string $label,
        public Direction $dir = Direction::Ltr,
        public bool $default = false,
    ) {}

    public static function fromArray(array $data): self
    {
        $code = (string) ($data['code'] ?? 'en');
        $dir = isset($data['dir']) ? Direction::tryFrom((string) $data['dir']) : null;

        return new self(
            code: $code,
            label: (string) ($data['label'] ?? self::defaultLabel($code)),
            dir: $dir ?? Direction::forLocale($code),
            default: (bool) ($data['default'] ?? false),
        );
    }

    public static function fallback(string $code = 'en'): self
    {
        return new self($code, self::defaultLabel($code), Direction::forLocale($code), true);
    }

    public function toArray(): array
    {
        return ['code' => $this->code, 'label' => $this->label, 'dir' => $this->dir->value, 'default' => $this->default];
    }

    private static function defaultLabel(string $code): string
    {
        return match (strtolower(explode('-', $code)[0])) {
            'en' => 'English',
            'ar' => 'العربية',
            'fr' => 'Français',
            'de' => 'Deutsch',
            'es' => 'Español',
            'pt' => 'Português',
            'tr' => 'Türkçe',
            'nl' => 'Nederlands',
            'it' => 'Italiano',
            'ja' => '日本語',
            'zh' => '中文',
            'ko' => '한국어',
            'ru' => 'Русский',
            'fa' => 'فارسی',
            'he' => 'עברית',
            'ur' => 'اردو',
            default => strtoupper($code),
        };
    }
}
