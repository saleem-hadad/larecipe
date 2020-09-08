<?php

namespace BinaryTorch\LaRecipe\Traits;

use Symfony\Component\Process\Process;

trait RunProcess
{
    /**
     * Run the given command as a process.
     *
     * @param  string  $command
     * @param  string  $path
     * @return void
     */
    protected function runProcess($command, $path)
    {
        $process = (new Process(explode(" ", $command), $path))->setTimeout(null);

        if ('\\' !== DIRECTORY_SEPARATOR && file_exists('/dev/tty') && is_readable('/dev/tty')) {
            $process->setTty(true);
        }

        $process->run(function ($type, $line) {
            $this->output->write($line);
        });
    }
}
