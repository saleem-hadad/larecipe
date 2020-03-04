<?php

namespace BinaryTorch\LaRecipe\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use BinaryTorch\LaRecipe\LaRecipeServiceProvider;

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
        $this->line('Publishing assets and congigurations.. 🍪');
        $this->call('vendor:publish', ['--provider' => LaRecipeServiceProvider::class, '--tag' => ['larecipe_assets', 'larecipe_config', 'larecipe_views']]);

        $this->line('Setup initial documentations structure under '.config('larecipe.docs.path').'.. 🍪');
        $this->call('larecipe:docs');

        $this->line('Dumping the autoloaded files and reloading all new files.. 🍪');
        $composer = $this->findComposer();
        $process = new Process(app()::VERSION[0]>6  ? [$composer.' dump-autoload'] : $composer.' dump-autoload') ;
        $process->setTimeout(null);
        $process->setWorkingDirectory(base_path())->run();

        $this->info('LaRecipe successfully installed! Enjoy 😍');
        $this->info('Visit /docs in your browser 👻');
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" '.getcwd().'/composer.phar';
        }

        return 'composer';
    }
}
