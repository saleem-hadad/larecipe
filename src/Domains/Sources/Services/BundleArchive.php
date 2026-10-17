<?php

namespace LaRecipe\Domains\Sources\Services;

use FilesystemIterator;
use Phar;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/**
 * Packs a bundle directory into a .tar.gz and back, with PHP's own PharData,
 * so exports and imports need no tar binary on the server.
 */
final class BundleArchive
{
    public function pack(string $directory, string $archive): void
    {
        $tar = sys_get_temp_dir().'/larecipe-bundle-'.bin2hex(random_bytes(8)).'.tar';
        $data = new PharData($tar);
        $data->buildFromDirectory($directory);
        $data->compress(Phar::GZ);
        unset($data);

        if (! is_dir(dirname($archive))) {
            mkdir(dirname($archive), 0755, true);
        }

        rename($tar.'.gz', $archive);
        @unlink($tar);
    }

    public function unpack(string $archive, string $directory): void
    {
        if (! is_file($archive)) {
            throw new RuntimeException("There is no bundle at [{$archive}].");
        }

        (new PharData($archive))->extractTo($directory, null, true);
    }

    /** Remove a temporary bundle directory and everything in it. */
    public function remove(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($directory);
    }
}
