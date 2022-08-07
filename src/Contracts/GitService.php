<?php

namespace BinaryTorch\LaRecipe\Contracts;

use Illuminate\Support\Collection;

interface GitService
{ 
    public function isGitInstalled(): bool;

    public function getFileShortlog(string $filePath): Collection;
}