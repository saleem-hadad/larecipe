<?php

namespace LaRecipe\Domains\Delivery\Support;

use LaRecipe\Domains\Content\DTOs\Theme;

/**
 * Turns a set's theme tokens from larecipe.json into CSS variables. Only what the
 * team overrode is emitted; the design system's defaults live in the UI build.
 */
final class ThemeCss
{
    public function render(Theme $theme): string
    {
        $vars = [];

        if ($theme->accent !== null && $this->isSafe($theme->accent)) {
            $vars['--lr-accent'] = $theme->accent;
            $vars['--lr-accent-dark'] = "oklch(from {$theme->accent} calc(l + 0.2) calc(c * 0.72) h)";
        }

        if ($theme->radius === 'soft') {
            $vars['--lr-radius-sm'] = '6px';
            $vars['--lr-radius-md'] = '10px';
            $vars['--lr-radius-lg'] = '14px';
        }

        foreach (['sans' => $theme->fontSans, 'mono' => $theme->fontMono, 'arabic' => $theme->fontArabic] as $kind => $font) {
            if ($font !== null && $this->isSafe($font)) {
                $vars["--lr-font-{$kind}-custom"] = "'".str_replace("'", '', $font)."'";
            }
        }

        if ($vars === []) {
            return '';
        }

        $declarations = implode('', array_map(fn ($name, $value) => "{$name}:{$value};", array_keys($vars), $vars));

        return ":root{{$declarations}}";
    }

    /** Token values come from content files; never let them close the style tag or inject rules. */
    private function isSafe(string $value): bool
    {
        return preg_match('/^[#\w\s.,%()+\-\/\'"]+$/u', $value) === 1 && ! str_contains($value, '</');
    }
}
