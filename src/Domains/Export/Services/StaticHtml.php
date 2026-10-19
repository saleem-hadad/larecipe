<?php

namespace LaRecipe\Domains\Export\Services;

/**
 * Makes a server-rendered page work as a static file.
 *
 *   - links to exported pages end in a slash, so static hosts serve
 *     folder/index.html without a redirect;
 *   - a base URL with a path (https://acme.github.io/docs) prefixes every
 *     root-relative link and asset;
 *   - CSRF tokens are emptied (there is no server to check them);
 *   - <html> is marked data-lr-static, so the UI does full page loads instead of
 *     fetching JSON a static host cannot negotiate.
 */
final class StaticHtml
{
    /**
     * @param  array<string, true>  $pages  domain-relative paths of every exported HTML page
     * @param  string  $origin  the origin absolute URLs were rendered with ("https://acme.github.io")
     */
    public function __construct(
        private readonly array $pages,
        private readonly string $basePath = '',
        private readonly string $origin = '',
    ) {}

    public function page(string $html): string
    {
        $html = $this->text($html);
        $html = (string) preg_replace('/<html\b(?![^>]*data-lr-static)/i', '<html data-lr-static', $html, 1);
        $html = (string) preg_replace('/(<input type="hidden" name="_token" value=")[^"]*(")/', '$1$2', $html);
        $html = (string) preg_replace_callback('/\b(href|src|action)="(\/[^"]*)"/', fn (array $m) => $m[1].'="'.$this->url($m[2]).'"', $html);
        $html = (string) preg_replace_callback('/(content="\d+;\s*url=)(\/[^"]*)"/i', fn (array $m) => $m[1].$this->url($m[2]).'"', $html);

        return (string) preg_replace_callback(
            '#(<script type="application/json" id="lr-boot">)(.*?)(</script>)#s',
            fn (array $m) => $m[1].$this->boot($m[2]).$m[3],
            $html,
        );
    }

    /** Absolute URLs in any text file (agent Markdown, llms.txt, the sitemap) gain the base URL's path. */
    public function text(string $text): string
    {
        if ($this->basePath === '' || $this->origin === '') {
            return $text;
        }

        return str_replace($this->origin.'/', $this->origin.$this->basePath.'/', $text);
    }

    /** Root-relative URLs a page needs (styles, scripts, images, search indexes), for the asset pass. */
    public static function references(string $html): array
    {
        preg_match_all('/\b(?:href|src)="(\/[^"#?]*)/', $html, $attributes);
        preg_match_all('#"(/[^"]*_search/[^"]*\.json)"#', str_replace('\\/', '/', $html), $indexes);

        return array_values(array_unique(array_merge($attributes[1], $indexes[1])));
    }

    private function boot(string $json): string
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return $json;
        }

        array_walk_recursive($data, function (&$value) {
            if (is_string($value) && str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
                $value = $this->url($value);
            }
        });

        return (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }

    private function url(string $url): string
    {
        if (str_starts_with($url, '//')) {
            return $url;
        }

        $path = (string) preg_replace('/[?#].*$/', '', $url);
        $suffix = substr($url, strlen($path));

        if (isset($this->pages[$path]) && $path !== '/' && ! str_ends_with($path, '/')) {
            $path .= '/';
        }

        return $this->basePath.$path.$suffix;
    }
}
