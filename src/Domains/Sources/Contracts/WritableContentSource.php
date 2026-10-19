<?php

namespace LaRecipe\Domains\Sources\Contracts;

use LaRecipe\Domains\Content\DTOs\Manifest;
use LaRecipe\Domains\Content\DTOs\NavigationTree;
use LaRecipe\Domains\Content\DTOs\Page;
use LaRecipe\Domains\Content\DTOs\PageRef;
use LaRecipe\Domains\Content\DTOs\Provenance;
use LaRecipe\Domains\Sources\DTOs\Revision;
use LaRecipe\Domains\Sources\DTOs\WriteContext;
use LaRecipe\Domains\Sources\Enums\WriteMode;

/**
 * A source LaRecipe can write to. Never called directly by other domains: every
 * writer goes through the Sources Actions (WritePageAction and friends), which
 * validate, apply the review mode and dispatch events first.
 *
 * The optional WriteContext carries the channel, summary, basedOn hash and docs
 * migration of the change, so a source that keeps revisions can record them.
 */
interface WritableContentSource extends ContentSource
{
    public function writePage(Page $page, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision;

    public function movePage(PageRef $from, PageRef $to, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision;

    public function deletePage(PageRef $ref, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision;

    public function writeNavigation(string $version, string $locale, NavigationTree $tree, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision;

    /** Remove a version's navigation.json, so its navigation is derived from folders again. */
    public function removeNavigation(string $version, string $locale, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision;

    public function writeManifest(Manifest $manifest, Provenance $by, WriteMode $mode = WriteMode::Publish, ?WriteContext $context = null): Revision;

    public function writeAsset(string $version, string $path, string $contents): void;

    public function deleteAsset(string $version, string $path): void;

    /**
     * Every asset of one version, as paths relative to the version root
     * ("_assets/flow.png", "theme.css"). Version::IMPLICIT lists the set-level
     * assets of a versioned set. Source moves and exports enumerate them here.
     *
     * @return iterable<string>
     */
    public function assets(string $version): iterable;
}
