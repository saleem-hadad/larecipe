<?php

namespace BinaryTorch\LaRecipe\Commands;

use Illuminate\Console\Command;
use BinaryTorch\LaRecipe\Models\Documentation;

class IndexDocumentationCommand extends Command
{
    /**
     * @var Documentation
     */
    protected $documentation;

    /**
     * Create a new documentation instance.
     *
     * @param  Filesystem  $files
     * @return void
     */
    public function __construct(Documentation $documentation)
    {
        parent::__construct();
        $this->documentation = $documentation;
    }

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
    protected $description = 'Index docmentation for fast built-in search functionality.';

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

            $this->documentation->getIndex($version);
        }
    }
}
