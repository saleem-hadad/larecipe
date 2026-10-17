<?php

namespace LaRecipe\Domains\Delivery\Controllers;

use Illuminate\Http\Request;
use LaRecipe\Domains\Delivery\Support\Assets;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the prebuilt UI from the package's dist/ folder, so a host app needs no
 * Node and no publish step. Filenames are content-hashed, so responses are
 * immutable; precompressed siblings are used when the browser accepts them.
 */
final class PackageAssetController
{
    public function __construct(private readonly Assets $assets) {}

    public function __invoke(Request $request, string $build, string $file): Response
    {
        $path = $this->assets->file($file);

        if ($path === null) {
            abort(404);
        }

        $encoding = null;
        $accepts = (string) $request->header('Accept-Encoding');

        foreach (['br' => '.br', 'gzip' => '.gz'] as $name => $suffix) {
            if (str_contains($accepts, $name) && is_file($path.$suffix)) {
                $encoding = $name;
                $served = $path.$suffix;

                break;
            }
        }

        $response = new BinaryFileResponse($served ?? $path, 200, [
            'Content-Type' => $this->mime($path),
            'Cache-Control' => $build === $this->assets->build() ? 'public, max-age=31536000, immutable' : 'public, max-age=60',
            'Vary' => 'Accept-Encoding',
        ]);

        if ($encoding !== null) {
            $response->headers->set('Content-Encoding', $encoding);
        }

        return $response;
    }

    private function mime(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'js', 'mjs' => 'text/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'json', 'map' => 'application/json',
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }
}
