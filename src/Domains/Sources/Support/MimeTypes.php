<?php

namespace LaRecipe\Domains\Sources\Support;

/**
 * Content types for docs assets, by extension. Shared by both drivers so an
 * asset is served with the same type wherever it is stored.
 */
final class MimeTypes
{
    public static function for(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'css' => 'text/css',
            'js' => 'text/javascript',
            'json' => 'application/json',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'pdf' => 'application/pdf',
            'woff2' => 'font/woff2',
            'txt' => 'text/plain',
            default => 'application/octet-stream',
        };
    }
}
