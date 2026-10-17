<?php

namespace LaRecipe\Domains\Agents\Commands;

use Illuminate\Console\Command;

final class DoctorCommand extends Command
{
    protected $signature = 'larecipe:doctor {--set=} {--json}';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
