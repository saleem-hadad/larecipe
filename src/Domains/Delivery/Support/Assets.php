<?php

namespace LaRecipe\Domains\Delivery\Support;

use Illuminate\Support\HtmlString;
use LaRecipe\LaRecipe;
use LaRecipe\Support\Paths;

/**
 * The prebuilt UI, served from the package's dist/ folder (the host needs no
 * Node). Reads Vite's manifest and emits the tags, the preloads and the import
 * map that lets host extensions share the runtime's single copy of React.
 *
 * It also finds the host's own extension build (custom React components built
 * by @larecipe/vite into public/build/larecipe/) and loads it right after the
 * LaRecipe runtime, so the host never wires a script tag by hand.
 */
final class Assets
{
    /** Bare specifiers host extensions import, and the dist entry that provides each. */
    public const SHARED_MODULES = [
        'react' => 'react',
        'react/jsx-runtime' => 'jsx-runtime',
        'react/jsx-dev-runtime' => 'jsx-runtime',
        'react-dom' => 'react-dom',
        'react-dom/client' => 'react-dom-client',
        '@larecipe/ui' => 'larecipe-ui',
    ];

    /** Where @larecipe/vite writes a host extension, relative to the public path, and the URL it is served from. */
    public const EXTENSION_DIRECTORY = 'build/larecipe';

    /** Font files worth preloading: the Latin cuts the first paint needs, so text never reflows when they arrive. */
    private const PRELOAD_FONTS = ['inter-latin-wght-normal', 'jetbrains-mono-latin-400-normal', 'jetbrains-mono-latin-500-normal'];

    private ?array $manifest = null;

    private ?string $build = null;

    /** @var array<string, mixed>|false|null false once we know there is no extension */
    private array|false|null $extensionManifest = null;

    public function __construct(
        private readonly LaRecipe $larecipe,
        private readonly ?string $devServer = null,
        private readonly ?string $publicPath = null,
        private readonly ?string $distPath = null,
        private ?string $extensionPath = null,
        private ?string $extensionUrl = null,
    ) {}

    /**
     * Point at a host extension build somewhere other than public/build/larecipe
     * (a custom outDir in @larecipe/vite, or a Testbench workbench).
     */
    public function useExtension(string $directory, string $url): void
    {
        $this->extensionPath = rtrim($directory, '/');
        $this->extensionUrl = rtrim($url, '/');
        $this->extensionManifest = null;
    }

    /**
     * The host extension's build folder and the URL it is served from, or null
     * when the host has not built one. The static export copies it whole: an
     * extension loads chunks of its own that no page names.
     *
     * @return array{directory: string, url: string}|null
     */
    public function extensionBuild(): ?array
    {
        if ($this->extensionManifest() === null) {
            return null;
        }

        return [
            'directory' => $this->extensionPath ?? $this->publicPath.'/'.self::EXTENSION_DIRECTORY,
            'url' => $this->extensionUrl ?? '/'.self::EXTENSION_DIRECTORY,
        ];
    }

    public function isDev(): bool
    {
        return $this->devServer !== null && $this->devServer !== '';
    }

    public function exists(): bool
    {
        return $this->isDev() || $this->manifest() !== [];
    }

    public function build(): string
    {
        if ($this->isDev()) {
            return 'dev';
        }

        return $this->build ??= is_file($file = $this->dist('.vite/manifest.json'))
            ? substr(hash_file('xxh128', $file) ?: 'missing', 0, 12)
            : 'missing';
    }

    public function url(string $file): string
    {
        if ($this->usesPublished()) {
            return '/vendor/larecipe/'.ltrim($file, '/');
        }

        return '/larecipe-assets/'.$this->build().'/'.ltrim($file, '/');
    }

    /** Head tags for one entry ("app" for readers, "admin" for the admin UI). */
    public function tags(string $entry = 'app'): HtmlString
    {
        $nonce = $this->nonceAttribute();

        if ($this->isDev()) {
            $dev = rtrim((string) $this->devServer, '/');
            $source = $entry === 'admin' ? 'src/admin/main.tsx' : 'src/app/main.tsx';

            return new HtmlString(implode("\n", array_filter([
                $this->importMapTag(),
                "<script type=\"module\"{$nonce}>import RefreshRuntime from '{$dev}/@react-refresh'; RefreshRuntime.injectIntoGlobalHook(window); window.\$RefreshReg\$ = () => {}; window.\$RefreshSig\$ = () => (type) => type; window.__vite_plugin_react_preamble_installed__ = true;</script>",
                "<script type=\"module\" src=\"{$dev}/@vite/client\"{$nonce}></script>",
                "<script type=\"module\" src=\"{$dev}/{$source}\"{$nonce}></script>",
                $entry === 'app' ? $this->extensionTags() : '',
            ])));
        }

        $chunk = $this->entry($entry);

        if ($chunk === null) {
            return new HtmlString('<!-- LaRecipe UI is not built: run pnpm build in the package -->');
        }

        $tags = [$this->importMapTag()];

        foreach ($this->fontPreloads() as $font) {
            $tags[] = '<link rel="preload" as="font" type="font/woff2" crossorigin href="'.e($this->url($font)).'">';
        }

        foreach ($this->css($chunk) as $css) {
            $tags[] = '<link rel="stylesheet" href="'.e($this->url($css)).'">';
        }

        foreach ($this->imports($chunk) as $import) {
            $tags[] = '<link rel="modulepreload" href="'.e($this->url($import)).'">';
        }

        $tags[] = '<script type="module" src="'.e($this->url($chunk['file'])).'"'.$nonce.'></script>';

        if ($entry === 'app' && ($extension = $this->extensionTags()) !== '') {
            $tags[] = $extension;
        }

        return new HtmlString(implode("\n", $tags));
    }

    /**
     * The host extension's tags: its stylesheets and its module, loaded after
     * the runtime. Its imports of react, react-dom and @larecipe/ui resolve
     * through the import map to the runtime's own copies.
     */
    public function extensionTags(): string
    {
        $entry = $this->extensionEntry();

        if ($entry === null) {
            return '';
        }

        $tags = [];

        foreach ($entry['css'] as $css) {
            $tags[] = '<link rel="stylesheet" href="'.e($css).'">';
        }

        foreach ($entry['imports'] as $import) {
            $tags[] = '<link rel="modulepreload" href="'.e($import).'">';
        }

        $tags[] = '<script type="module" src="'.e($entry['file']).'" data-lr-extension'.$this->nonceAttribute().'></script>';

        return implode("\n", $tags);
    }

    /**
     * The host extension's entry as URLs, or null when the host has not built one.
     *
     * @return array{file: string, css: list<string>, imports: list<string>}|null
     */
    public function extensionEntry(): ?array
    {
        $manifest = $this->extensionManifest();

        if ($manifest === null) {
            return null;
        }

        $base = $this->extensionUrl ?? '/'.self::EXTENSION_DIRECTORY;

        foreach ($manifest as $chunk) {
            if (! is_array($chunk) || ! ($chunk['isEntry'] ?? false) || ! isset($chunk['file'])) {
                continue;
            }

            $css = [];
            $imports = [];

            foreach ($chunk['css'] ?? [] as $file) {
                $css[] = $base.'/'.ltrim((string) $file, '/');
            }

            foreach ($chunk['imports'] ?? [] as $key) {
                if (isset($manifest[$key]['file'])) {
                    $imports[] = $base.'/'.ltrim((string) $manifest[$key]['file'], '/');
                }

                foreach ($manifest[$key]['css'] ?? [] as $file) {
                    $css[] = $base.'/'.ltrim((string) $file, '/');
                }
            }

            return [
                'file' => $base.'/'.ltrim((string) $chunk['file'], '/'),
                'css' => array_values(array_unique($css)),
                'imports' => array_values(array_unique($imports)),
            ];
        }

        return null;
    }

    /** @return array<string, string> specifier => URL */
    public function importMap(): array
    {
        $map = [];

        if ($this->isDev()) {
            $dev = rtrim((string) $this->devServer, '/');

            foreach (self::SHARED_MODULES as $specifier => $entry) {
                $map[$specifier] = "{$dev}/src/shims/{$entry}.ts";
            }

            return $map;
        }

        foreach (self::SHARED_MODULES as $specifier => $entry) {
            $chunk = $this->entry($entry);

            if ($chunk !== null) {
                $map[$specifier] = $this->url($chunk['file']);
            }
        }

        return $map;
    }

    public function importMapTag(): string
    {
        $json = json_encode(['imports' => $this->importMap()], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

        return '<script type="importmap"'.$this->nonceAttribute().'>'.$json.'</script>';
    }

    /** Absolute path of a built file inside dist/, or null when it does not exist or escapes dist/. */
    public function file(string $path): ?string
    {
        $base = realpath($this->dist());
        $file = realpath($this->dist($path));

        if ($base === false || $file === false || ! str_starts_with($file, $base.DIRECTORY_SEPARATOR) || ! is_file($file)) {
            return null;
        }

        return $file;
    }

    public function dist(string $path = ''): string
    {
        $base = $this->distPath ?? Paths::dist();

        return $path === '' ? $base : $base.'/'.ltrim($path, '/');
    }

    private function manifest(): array
    {
        if ($this->manifest === null) {
            $file = $this->dist('.vite/manifest.json');
            $this->manifest = is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
        }

        return $this->manifest;
    }

    /** @return array<string, mixed>|null */
    private function extensionManifest(): ?array
    {
        if ($this->extensionManifest === null) {
            $directory = $this->extensionPath ?? ($this->publicPath !== null ? $this->publicPath.'/'.self::EXTENSION_DIRECTORY : null);
            $file = $directory !== null ? $directory.'/manifest.json' : null;
            $data = $file !== null && is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

            $this->extensionManifest = is_array($data) && $data !== [] ? $data : false;
        }

        return $this->extensionManifest === false ? null : $this->extensionManifest;
    }

    private function entry(string $name): ?array
    {
        foreach ($this->manifest() as $chunk) {
            if (($chunk['isEntry'] ?? false) && ($chunk['name'] ?? null) === $name) {
                return $chunk;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function fontPreloads(): array
    {
        $fonts = [];

        foreach ($this->manifest() as $chunk) {
            foreach ($chunk['assets'] ?? [] as $asset) {
                foreach (self::PRELOAD_FONTS as $font) {
                    if (str_contains((string) $asset, $font) && str_ends_with((string) $asset, '.woff2')) {
                        $fonts[$asset] = true;
                    }
                }
            }
        }

        return array_keys($fonts);
    }

    /** @return list<string> */
    private function imports(array $chunk, array &$seen = []): array
    {
        $files = [];

        foreach ($chunk['imports'] ?? [] as $key) {
            if (isset($seen[$key]) || ! isset($this->manifest()[$key])) {
                continue;
            }

            $seen[$key] = true;
            $imported = $this->manifest()[$key];
            $files[] = $imported['file'];
            array_push($files, ...$this->imports($imported, $seen));
        }

        return array_values(array_unique($files));
    }

    /** @return list<string> */
    private function css(array $chunk, array &$seen = []): array
    {
        $files = $chunk['css'] ?? [];

        foreach ($chunk['imports'] ?? [] as $key) {
            if (isset($seen[$key]) || ! isset($this->manifest()[$key])) {
                continue;
            }

            $seen[$key] = true;
            array_push($files, ...$this->css($this->manifest()[$key], $seen));
        }

        return array_values(array_unique($files));
    }

    private function usesPublished(): bool
    {
        if ($this->publicPath === null) {
            return false;
        }

        $published = $this->publicPath.'/vendor/larecipe/.vite/manifest.json';
        $packaged = $this->dist('.vite/manifest.json');

        return is_file($published) && is_file($packaged) && hash_file('xxh128', $published) === hash_file('xxh128', $packaged);
    }

    private function nonceAttribute(): string
    {
        $nonce = $this->larecipe->cspNonce();

        return $nonce === null ? '' : ' nonce="'.e($nonce).'"';
    }
}
