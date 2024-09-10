<?php

namespace SaleemHadad\LaRecipe\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use SaleemHadad\LaRecipe\LaRecipeServiceProvider;

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
        $this->newLine();
        $this->line('STEP 1/3: Publishing assets and congigurations.. 🍪');
        $this->call('vendor:publish', ['--provider' => LaRecipeServiceProvider::class, '--tag' => ['larecipe_assets', 'larecipe_config', 'larecipe_views']]);
        $this->newLine(2);
        sleep(2);

        $this->line('STEP 2/3: Setup initial docs structure under ' . config('larecipe.source') . '.. 🍪');
        $this->call('larecipe:docs');
        $this->newLine(2);
        sleep(2);

        $this->line('STEP 3/3: Finishing up.. 🍪');
        $composer = $this->findComposer();
        $appVersion = explode('.', app()::VERSION);
        $process = new Process($appVersion[0]>6  ? [$composer.' dump-autoload'] : $composer.' dump-autoload') ;
        $process->setTimeout(null);
        $process->setWorkingDirectory(base_path())->run();

        $this->info('LaRecipe has been successfully installed! Enjoy 😍');
        $this->info('Visit ' . config('larecipe.path') . ' in your browser 👻');
        $this->newLine(2);

        $this->line('Support the project if you love it 👻');
        $this->info('https://opencollective.com/larecipe');
        $this->newLine();
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
