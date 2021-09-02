<?php

namespace BinaryTorch\LaRecipe\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

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
    protected $description = 'Generate docs structure';

    /**
     * The Filesystem instance.
     *
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * Create a new command instance.
     *
     * @param Filesystem $filesystem
     */
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        $isLanguageEnabled = config('larecipe.languages.enabled');
        $publishedLanguages = config('larecipe.languages.published');

        if($isLanguageEnabled) {
            $this->info('Lanugages enabled. Reading all supported languages, found: '.implode(',', $publishedLanguages));
            foreach($publishedLanguages as $language) {
                $this->renderVersions();
            }

            return;
        }

        $this->renderVersions();
    }

    protected function renderVersions()
    {
        $isVersionEnabled = config('larecipe.versions.enabled');
        $publishedVersions = config('larecipe.versions.published');

        if($isVersionEnabled){ 
            $this->info('Versioning enabled. Reading all supported versions, found: '.implode(',', $publishedVersions));

            foreach($publishedVersions as $version) {
                $this->renderDocs();
            }

            return;
        }

        $this->renderDocs();
    }

    protected function renderDocs()
    {
        $this->line('');
        $this->info('---------------- Version '.$version.' ----------------');
        // check if the version directory not exists => create one
        if ($this->createVersionDirectory(base_path($versionDirectory))) {
            $this->line('Docs folder created for v'.$version.' under '.$versionDirectory);
        } else {
            $this->line('Docs folder for <info>v'.$version.'</info> already exists.');
        }

        // check if the version index.md not exists => create one
        if ($this->createVersionIndex(base_path($versionDirectory))) {
            $this->line('index.md created under '.$versionDirectory);
        } else {
            $this->line('<info>index.md</info> for <info>v'.$version.'</info> already exists.');
        }

        // // check if the version landing page not exists => create one
        if ($this->createVersionLanding(base_path($versionDirectory))) {
            $this->line(config('larecipe.landing').'.md created under '.$versionDirectory);
        } else {
            $this->line('<info>'.config('larecipe.landing').'.md</info> for <info>v'.$version.'</info> already exists.');
        }

        $this->info('--------------- /Version '.$version.' ----------------');
        $this->line('');
    }

    /**
     * Create a new directory for the given version if not exists.
     *
     * @return bool
     */
    protected function createVersionDirectory($versionDirectory)
    {
        if (! $this->filesystem->isDirectory($versionDirectory)) {
            $this->filesystem->makeDirectory($versionDirectory, 0755, true);

            return true;
        }

        return false;
    }

    /**
     * Create index.md for the given version if it's not exists.
     *
     * @param $versionDirectory
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function createVersionIndex($versionDirectory)
    {
        $indexPath = $versionDirectory.'/index.md';

        if (! $this->filesystem->exists($indexPath)) {
            $content = $this->generateIndexContent($this->getStub('index'));
            $this->filesystem->put($indexPath, $content);

            return true;
        }

        return false;
    }

    /**
     * Create {landing}.md for the given version if it's not exists.
     *
     * @param $versionDirectory
     * @return bool
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function createVersionLanding($versionDirectory)
    {
        $landingPath = $versionDirectory.'/'.config('larecipe.landing').'.md';

        if (! $this->filesystem->exists($landingPath)) {
            $content = $this->generateLandingContent($this->getStub('landing'));
            $this->filesystem->put($landingPath, $content);

            return true;
        }

        return false;
    }

    /**
     * replace stub placeholders.
     *
     * @return string
     */
    protected function generateIndexContent($stub)
    {
        $content = str_replace(
            '{{LANDING}}',
            ucwords(config('larecipe.landing')),
            $stub
        );

        $content = str_replace(
            '{{LANDINGSMALL}}',
            trim(config('larecipe.landing'), '/'),
            $content
        );

        return $content;
    }

    /**
     * replace stub placeholders.
     *
     * @return string
     */
    protected function generateLandingContent($stub)
    {
        return str_replace(
            '{{TITLE}}',
            ucwords(config('larecipe.landing')),
            $stub
        );
    }

    /**
     * Get the stub file for the generator.
     *
     * @param $stub
     * @return string
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function getStub($stub)
    {
        return $this->filesystem->get(base_path('/vendor/binarytorch/larecipe/stubs/'.$stub.'.stub'));
    }
}
