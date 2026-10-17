<?php

namespace TrackGApp\Console\Commands;

use Illuminate\Console\Command;

/** An internal command the app hides from `php artisan list`. */
class RotateKeysCommand extends Command
{
    protected $signature = 'keys:rotate-internal {--vault=primary}';

    protected $description = 'Rotate the internal signing keys';

    protected $hidden = true;

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
