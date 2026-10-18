<?php

namespace TrackGApp\Console\Commands;

use Illuminate\Console\Command;

/** Defaults written in the signature: safe to show. */
class SendReportsCommand extends Command
{
    protected $signature = 'reports:send {period=weekly : Which reports} {--queue=reports : The queue to use} {--region= : Only one region}';

    protected $description = 'Send the scheduled reports';

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
