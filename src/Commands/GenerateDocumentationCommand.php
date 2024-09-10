<?php

namespace SaleemHadad\LaRecipe\Commands;

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
     */
    public function handle()
    {
        $this->processLanguages()
            ->processVersions()
            ->processDocumentation();
    }

    /**
     * @return void
     */
    public function processLanguages(): GenerateDocumentationCommand
    {
        $isLanguageEnabled = config('larecipe.languages.enabled');
        $publishedLanguages = config('larecipe.languages.published');

        if ($isLanguageEnabled) {
            foreach ($publishedLanguages as $language) {
                $languageDir = base_path(config('larecipe.source') . '/' . $language);
                if (!$this->filesystem->isDirectory($languageDir)) {
                    $this->filesystem->makeDirectory($languageDir, 0755, true);
                }
            }
        }

        return $this;
    }

    protected function processVersions(): GenerateDocumentationCommand
    {
        $isVersionEnabled = config('larecipe.versions.enabled');
        $publishedVersions = config('larecipe.versions.published');
        $isLanguageEnabled = config('larecipe.languages.enabled');
        $publishedLanguages = config('larecipe.languages.published');

        if($isVersionEnabled){
            if ($isLanguageEnabled) {
                foreach ($publishedLanguages as $language) {
                    $languageDir = config('larecipe.source') . '/' . $language . '/';
                    foreach($publishedVersions as $version) {
                        $dir = base_path($languageDir . '/' . $version);
                        if (!$this->filesystem->isDirectory($dir)) {
                            $this->filesystem->makeDirectory($dir, 0755, true);
                        }
                    }
                }
            }else {
                foreach($publishedVersions as $version) {
                    $dir = base_path(config('larecipe.source') . '/' . $version);
                    if (!$this->filesystem->isDirectory($dir)) {
                        $this->filesystem->makeDirectory($dir, 0755, true);
                    }
                }
            }
        }

        return $this;
    }

    protected function processDocumentation(): GenerateDocumentationCommand
    {
        $isVersionEnabled = config('larecipe.versions.enabled');
        $publishedVersions = config('larecipe.versions.published');
        $isLanguageEnabled = config('larecipe.languages.enabled');
        $publishedLanguages = config('larecipe.languages.published');

        if($isVersionEnabled){
            if ($isLanguageEnabled) {
                foreach ($publishedLanguages as $language) {
                    $languageDir = config('larecipe.source') . '/' . $language . '/';
                    foreach($publishedVersions as $version) {
                        $dir = base_path($languageDir . '/' . $version);
                        $this->createVersionIndex($dir);
                        $this->createVersionLanding($dir);
                    }
                }
            }else {
                foreach($publishedVersions as $version) {
                    $dir = base_path(config('larecipe.source') . '/' . $version);
                    $this->createVersionIndex($dir);
                    $this->createVersionLanding($dir);
                }
            }
        }

        return $this;
    }


    protected function createVersionIndex($dir)
    {
        $indexPath = $dir.'/index.md';

        if (! $this->filesystem->exists($indexPath)) {
            $content = $this->generateIndexContent($this->getStub('index'));
            $this->filesystem->put($indexPath, $content);

            return true;
        }

        return false;
    }

    protected function createVersionLanding($versionDirectory): bool
    {
        $landingPath = $versionDirectory.'/'.config('larecipe.landing').'.md';

        if (! $this->filesystem->exists($landingPath)) {
            $content = $this->generateLandingContent($this->getStub('landing'));
            $this->filesystem->put($landingPath, $content);

            return true;
        }

        return false;
    }

    protected function generateIndexContent($stub)
    {
        $content = str_replace(
            '{{LANDING}}',
            ucwords(config('larecipe.landing')),
            $stub
        );

        $content = str_replace(
            '{{LANDING_SLUG}}',
            trim(config('larecipe.landing'), '/'),
            $content
        );

        return $content;
    }

    protected function generateLandingContent($stub)
    {
        return str_replace(
            '{{TITLE}}',
            ucwords(config('larecipe.landing')),
            $stub
        );
    }

    protected function getStub($stub)
    {
        return $this->filesystem->get(base_path('/vendor/saleem-hadad/larecipe/stubs/'.$stub.'.stub'));
    }
}
