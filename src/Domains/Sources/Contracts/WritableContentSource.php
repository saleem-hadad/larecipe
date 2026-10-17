<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\Enums\WriteMode;

interface WritableContentSource extends ContentSource
{
    public function writePage(Page $page, Provenance $by, WriteMode $mode = WriteMode::Publish): Revision;

    public function movePage(PageRef $from, PageRef $to, Provenance $by, WriteMode $mode = WriteMode::Publish): Revision;

    public function deletePage(PageRef $ref, Provenance $by, WriteMode $mode = WriteMode::Publish): Revision;

    public function writeNavigation(string $version, string $locale, NavigationTree $tree, Provenance $by, WriteMode $mode = WriteMode::Publish): Revision;

    public function writeManifest(Manifest $manifest, Provenance $by, WriteMode $mode = WriteMode::Publish): Revision;

    public function writeAsset(string $version, string $path, string $contents): void;
}
