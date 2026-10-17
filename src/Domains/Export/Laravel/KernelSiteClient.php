<?php

namespace LaRecipe\Domains\Export\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use LaRecipe\Domains\Content\DTOs\DocSet;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Delivery\Services\DocsUrls;
use LaRecipe\Domains\Delivery\Support\Assets;
use LaRecipe\Domains\Export\Contracts\SiteClient;
use LaRecipe\Domains\Export\DTOs\ExportResponse;
use Throwable;

/**
 * Reads the site by handling each request in process through the app's own HTTP
 * kernel, as a signed-out reader. No server, no network: the export is exactly
 * what the app would answer, with the app's middleware, gates and renderers.
 */
final class KernelSiteClient implements SiteClient
{
    public const USER_AGENT = 'LaRecipe-Export/3 (static export)';

    public function __construct(
        private readonly Kernel $kernel,
        private readonly DocsUrls $urls,
        private readonly Assets $assets,
        private readonly string $origin,
        private readonly ?string $publicPath = null,
    ) {}

    public function get(string $path, string $accept = 'text/html'): ExportResponse
    {
        $request = Request::create($this->origin.'/'.ltrim($path, '/'), 'GET', server: [
            'HTTP_ACCEPT' => $accept,
            'HTTP_USER_AGENT' => self::USER_AGENT,
        ]);

        try {
            $response = $this->kernel->handle($request);
            $this->kernel->terminate($request, $response);
        } catch (Throwable) {
            return new ExportResponse(500, 'text/plain', '');
        }

        $body = method_exists($response, 'getFile') && $response->getFile() !== null
            ? (string) file_get_contents($response->getFile()->getPathname())
            : (string) $response->getContent();

        return new ExportResponse(
            $response->getStatusCode(),
            (string) $response->headers->get('Content-Type', ''),
            $body,
            $response->headers->get('Location'),
        );
    }

    public function pagePath(DocSet $set, PageRef $ref, bool $markdown = false): string
    {
        return $this->urls->pagePath($set, $ref, $markdown);
    }

    public function origin(): string
    {
        return $this->origin;
    }

    /** The built UI, from the package and, when published, from public/vendor/larecipe. */
    public function staticFolders(): array
    {
        $folders = [];

        if (! $this->assets->isDev() && is_dir($this->assets->dist())) {
            $folders['larecipe-assets/'.$this->assets->build()] = $this->assets->dist();
        }

        if ($this->publicPath !== null && is_dir($this->publicPath.'/vendor/larecipe')) {
            $folders['vendor/larecipe'] = $this->publicPath.'/vendor/larecipe';
        }

        return $folders;
    }
}
