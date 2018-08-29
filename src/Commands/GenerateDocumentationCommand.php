<?php

namespace BinaryTorch\LaRecipe\Commands;

use Illuminate\Console\Command;

class GenerateDocumentationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larecipe:docs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate docuementation.md for all your docs';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Processing all docs files.. 🍪');
    }
}
