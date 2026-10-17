<?php

namespace LaRecipe\Tests\Feature\Sync;

use FilesystemIterator;
use Illuminate\Foundation\Application;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * A throwaway Laravel-shaped project in a temporary folder, usually a git
 * repository, for tests that need real commits, branches and hooks.
 */
final class TemporaryProject
{
    private function __construct(public readonly string $root) {}

    public static function create(?string $fixture = null): self
    {
        // Keep the developer's own git config (global hooks paths, signing) out of the tests.
        foreach (['GIT_CONFIG_GLOBAL' => '/dev/null', 'GIT_CONFIG_NOSYSTEM' => '1'] as $name => $value) {
            putenv("{$name}={$value}");
            $_SERVER[$name] = $value;
        }

        $root = sys_get_temp_dir().'/larecipe-track-d-'.bin2hex(random_bytes(5));
        mkdir($root, 0755, true);
        $project = new self(realpath($root) ?: $root);

        if ($fixture !== null) {
            $project->copy(dirname(__DIR__, 2).'/Fixtures/track-d/'.$fixture);
        }

        return $project;
    }

    public function init(string $branch = 'main'): self
    {
        $this->git('init', '-q', '-b', $branch);
        $this->git('config', 'user.email', 'tests@larecipe.dev');
        $this->git('config', 'user.name', 'LaRecipe Tests');
        $this->git('config', 'commit.gpgsign', 'false');

        return $this;
    }

    public function git(string ...$arguments): string
    {
        $process = new Process(['git', ...$arguments], $this->root);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('git '.implode(' ', $arguments).' failed: '.$process->getErrorOutput());
        }

        return trim($process->getOutput());
    }

    public function write(string $path, string $contents): self
    {
        $file = $this->root.'/'.$path;

        if (! is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, $contents);

        return $this;
    }

    public function read(string $path): ?string
    {
        return is_file($this->root.'/'.$path) ? (string) file_get_contents($this->root.'/'.$path) : null;
    }

    public function append(string $path, string $contents): self
    {
        return $this->write($path, (string) $this->read($path).$contents);
    }

    public function delete(string $path): self
    {
        @unlink($this->root.'/'.$path);

        return $this;
    }

    public function commit(string $message = 'Change'): self
    {
        $this->git('add', '-A');
        $this->git('commit', '-q', '--allow-empty', '-m', $message);

        return $this;
    }

    public function branch(string $name): self
    {
        $this->git('checkout', '-q', '-b', $name);

        return $this;
    }

    /**
     * Point the test app at this project: its base path, storage, and one
     * repository doc set in docs/ unless other sets are given.
     *
     * @param  array<string, array<string, mixed>>|null  $sets
     * @param  array<string, mixed>  $config
     */
    public function mount(Application $app, ?array $sets = null, array $config = []): self
    {
        $app->setBasePath($this->root);
        $app->useStoragePath($this->root.'/storage');

        $app['config']->set('larecipe.default', 'docs');
        $app['config']->set('larecipe.sets', $sets ?? [
            'docs' => [
                'route' => ['prefix' => 'docs', 'middleware' => ['web']],
                'source' => ['driver' => 'repository', 'path' => $this->root.'/docs'],
                'access' => ['mode' => 'public'],
            ],
        ]);
        $app['config']->set('larecipe.migrations.path', $this->root.'/database/migrations/docs');

        foreach ($config as $key => $value) {
            $app['config']->set($key, $value);
        }

        $app->forgetScopedInstances();

        return $this;
    }

    public function path(string $relative = ''): string
    {
        return $relative === '' ? $this->root : $this->root.'/'.$relative;
    }

    public function copy(string $from): self
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            $target = $this->root.'/'.substr($item->getPathname(), strlen($from) + 1);

            if ($item->isDir()) {
                @mkdir($target, 0755, true);
            } else {
                @mkdir(dirname($target), 0755, true);
                copy($item->getPathname(), $target);
            }
        }

        return $this;
    }

    public function destroy(): void
    {
        if (! is_dir($this->root)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($this->root);
    }
}
