<?php

namespace BinaryTorch\LaRecipe\Tests\Fixtures;

use BinaryTorch\LaRecipe\Contracts\GitService as GitServiceContract;
use Illuminate\Support\Collection;

class DummyGitService implements GitServiceContract
{
    private $isGitInstalled;
    private $authorsArray;

    public function __construct(bool $isGitInstalled, array $authorsArray) {
        $this->isGitInstalled = $isGitInstalled;
        $this->authorsArray = $authorsArray;
    }
    
    public function isGitInstalled(): bool
    {
        return $this->isGitInstalled;
    }

    public function getFileShortlog(string $filePath): Collection
    {
        return collect($this->authorsArray);
    }
}