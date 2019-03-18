<?php

namespace BinaryTorch\LaRecipe\Tests;

use Illuminate\Support\Facades\Config;
use BinaryTorch\LaRecipe\Facades\LaRecipe;
use BinaryTorch\LaRecipe\LaRecipeServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setup(): void
    {
        parent::setup();
        
        $this->app->setBasePath(__DIR__ . '/../');

        $this->app['router']->get('login', function () { return 'login'; })->name('login');
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [LaRecipeServiceProvider::class];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
    }

    /**
     * Load package alias
     * @param  \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'LaRecipe' => LaRecipe::class,
        ];
    }
}
