<?php

namespace LaRecipe\Tests\Feature\Sources;

use LaRecipe\Domains\Content\Services\FrontmatterParser;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\Drivers\RepositorySource;
use LaRecipe\Tests\Contracts\ContentSourceContract;
use LaRecipe\Tests\Feature\Sources\Support\Fixtures;
use LaRecipe\Tests\TestCase;

/**
 * The repository driver against the shared source contract.
 */
final class RepositorySourceContractTest extends TestCase
{
    use ContentSourceContract;

    protected function emptySource(): WritableContentSource
    {
        return new RepositorySource('contract', Fixtures::temporaryDirectory('contract'), new FrontmatterParser);
    }
}
