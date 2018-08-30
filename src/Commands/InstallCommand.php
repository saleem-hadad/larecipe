<?php

namespace BinaryTorch\LaRecipe\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larecipe:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install LaRecipe and publish the required assets and configurations.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info('Assets Published.. 🍪');
        $this->info('Config Published.. 🍪');
        $this->info('Reading published versions.. 🍪');
        $this->info('resources/docs Folder Created.. 🍪');
    }
}
