<?php

namespace BinaryTorch\LaRecipe\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use BinaryTorch\LaRecipe\LaRecipeServiceProvider;

class IndexDocumentationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larecipe:index';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Index docmentation for fast search functionality.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->line('Reading published versions..');
        $publishedVersions = config('larecipe.versions.published');

        foreach ($publishedVersions as $version) {
            $this->info('Reading index.md for v'.$version);
            // $this->documentationIndex($version);
        }
    }
}
