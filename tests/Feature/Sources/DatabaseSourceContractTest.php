<?php

namespace LaRecipe\Tests\Feature\Sources;

use LaRecipe\Domains\Content\DTOs\SetConfig;
use LaRecipe\Domains\Sources\Contracts\WritableContentSource;
use LaRecipe\Domains\Sources\Laravel\DatabaseSourceFactory;
use LaRecipe\Tests\Contracts\ContentSourceContract;
use LaRecipe\Tests\Feature\Sources\Support\UsesDatabaseSets;
use LaRecipe\Tests\TestCase;

/**
 * The database driver, behind its cache exactly as it is served, against the
 * shared source contract.
 */
final class DatabaseSourceContractTest extends TestCase
{
    use ContentSourceContract;
    use UsesDatabaseSets;

    protected function emptySource(): WritableContentSource
    {
        return $this->app->make(DatabaseSourceFactory::class)->make(
            SetConfig::fromConfig('contract', ['source' => ['driver' => 'database', 'disk' => 'larecipe-assets']], []),
        );
    }
}
