<?php

namespace BinaryTorch\LaRecipe\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use BinaryTorch\LaRecipe\Traits\RunProcess;

class ThemeCommand extends Command
{
    use RunProcess;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'larecipe:theme {name} {--R|remove}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new LaRecipe theme';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        if (! $this->hasValidNameArgument()) {
            return;
        }

        if ($this->option('remove')) {
          $this->removeTheme();
          $this->info('Successfully removed LaRecipe theme.');
          return;
        }

        (new Filesystem)->copyDirectory(
            __DIR__.'/../../stubs/theme-stubs',
            $this->themePath()
        );

        // ThemeServiceProvider.php replacements...
        $this->replace('{{ namespace }}', $this->themeNamespace(), $this->themePath().'/src/ThemeServiceProvider.stub');
        $this->replace('{{ component }}', $this->themeName(), $this->themePath().'/src/ThemeServiceProvider.stub');
        $this->replace('{{ name }}', $this->themeName(), $this->themePath().'/src/ThemeServiceProvider.stub');

        // Theme composer.json replacements...
        $this->replace('{{ name }}', $this->argument('name'), $this->themePath().'/composer.json');
        $this->replace('{{ escapedNamespace }}', $this->escapedThemeNamespace(), $this->themePath().'/composer.json');

        // Rename the stubs with the proper file extensions...
        $this->renameStubs();

        // Register the theme...
        $this->addThemeRepositoryToRootComposer();
        $this->addThemePackageToRootComposer();

        if ($this->confirm('Would you like to update your Composer packages?', true)) {
            $this->composerUpdate();
        }
    }

    /**
     * Get the array of stubs that need PHP file extensions.
     *
     * @return array
     */
    protected function stubsToRename()
    {
        return [
            $this->themePath().'/src/ThemeServiceProvider.stub',
        ];
    }

    /**
     * Add a path repository for the theme to the application's composer.json file.
     *
     * @return void
     */
    protected function addThemeRepositoryToRootComposer()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $composer['repositories'][] = [
            'type' => 'path',
            'url' => './'.$this->relativeThemePath(),
        ];

        file_put_contents(
            base_path('composer.json'),
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Add a package entry for the theme to the application's composer.json file.
     *
     * @return void
     */
    protected function addThemePackageToRootComposer()
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        $composer['require'][$this->argument('name')] = '*';

        file_put_contents(
            base_path('composer.json'),
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    /**
     * Update the project's composer dependencies.
     *
     * @return void
     */
    protected function composerUpdate()
    {
        $this->runProcess('composer update', getcwd());
    }

    /**
     * Replace the given string in the given file.
     *
     * @param  string  $search
     * @param  string  $replace
     * @param  string  $path
     * @return void
     */
    protected function replace($search, $replace, $path)
    {
        file_put_contents($path, str_replace($search, $replace, file_get_contents($path)));
    }

    /**
     * Get the path to the theme.
     *
     * @return string
     */
    protected function themePath()
    {
        return base_path($this->relativeThemePath());
    }

    /**
     * Get the relative path to the theme.
     *
     * @return string
     */
    protected function relativeThemePath()
    {
        return config('larecipe.packages.path', 'larecipe-components').'/'.$this->themeClass();
    }

    /**
     * Get the theme's namespace.
     *
     * @return string
     */
    protected function themeNamespace()
    {
        return Str::studly($this->themeVendor()).'\\'.$this->themeClass();
    }

    /**
     * Get the theme's escaped namespace.
     *
     * @return string
     */
    protected function escapedThemeNamespace()
    {
        return str_replace('\\', '\\\\', $this->themeNamespace());
    }

    /**
     * Get the theme's class name.
     *
     * @return string
     */
    protected function themeClass()
    {
        return Str::studly($this->themeName());
    }

    /**
     * Get the theme's vendor.
     *
     * @return string
     */
    protected function themeVendor()
    {
        return explode('/', $this->argument('name'))[0];
    }

    /**
     * Get the theme's base name.
     *
     * @return string
     */
    protected function themeName()
    {
        return explode('/', $this->argument('name'))[1];
    }

    /**
     * Determine if the name argument is valid.
     *
     * @return bool
     */
    public function hasValidNameArgument()
    {
        $name = $this->argument('name');

        if (! Str::contains($name, '/')) {
            $this->error("The name argument expects a vendor and name in 'Composer' format. Here's an example: `vendor/name`.");

            return false;
        }

        return true;
    }

    /**
     * Rename the stubs with PHP file extensions.
     *
     * @return void
     */
    protected function renameStubs()
    {
        foreach ($this->stubsToRename() as $stub) {
            (new Filesystem)->move($stub, str_replace('.stub', '.php', $stub));
        }
    }

    /**
     * Remove the theme specified by vendor-name/theme
     *
     * @return void
     */
    protected function removeTheme()
    {
      $this->runProcess('composer remove '.$this->argument('name'), getcwd());
      (new Filesystem)->deleteDirectory($this->themePath());
    }
}
