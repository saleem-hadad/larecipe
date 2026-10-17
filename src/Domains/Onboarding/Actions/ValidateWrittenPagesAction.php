<?php

namespace LaRecipe\Domains\Onboarding\Actions;

use LaRecipe\Domains\Sources\Actions\GetPageAction;
use LaRecipe\Domains\Sync\Contracts\DocsCatalog;
use LaRecipe\Domains\Sync\DTOs\CatalogSet;
use LaRecipe\Domains\Validation\Actions\ValidatePageAction;
use LaRecipe\Domains\Validation\DTOs\ValidationReport;

/**
 * Validates the pages an onboarding run wrote, one by one, with the same page
 * rules every writer goes through. Used when the full `larecipe:validate`
 * command is not available.
 */
final class ValidateWrittenPagesAction
{
    public function __construct(
        private readonly DocsCatalog $catalog,
        private readonly GetPageAction $pages,
        private readonly ValidatePageAction $validate,
    ) {}

    /** @param list<string> $files project-relative files the run created or changed */
    public function execute(CatalogSet $set, array $files): ValidationReport
    {
        $report = new ValidationReport;

        foreach ($this->catalog->pages($set->key, $set->defaultVersion, $set->defaultLocale) as $summary) {
            if ($summary->file === null || ! in_array($summary->file, $files, true)) {
                continue;
            }

            $page = $this->pages->execute($summary->ref);

            if ($page !== null) {
                $report = $report->merge($this->validate->execute($page));
            }
        }

        return $report;
    }
}
