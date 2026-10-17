<?php

namespace TrackGApp\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputOption;

/** Options defined in code, with a default read from config. */
class ExportLedgerCommand extends Command
{
    protected $name = 'ledger:export';

    protected $description = 'Export the ledger';

    public function handle(): int
    {
        return self::SUCCESS;
    }

    protected function getOptions(): array
    {
        return [['disk', null, InputOption::VALUE_OPTIONAL, 'The disk to write to', config('services.ledger.disk')]];
    }
}
