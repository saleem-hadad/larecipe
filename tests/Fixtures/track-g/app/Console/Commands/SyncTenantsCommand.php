<?php

namespace TrackGApp\Console\Commands;

use Illuminate\Console\Command;

/** A default rebuilt from config at runtime, over the one in the signature. */
class SyncTenantsCommand extends Command
{
    protected $signature = 'tenants:sync {--region=eu : The region to sync} {--limit=100 : How many at once}';

    protected $description = 'Sync tenants from the billing system';

    public function __construct()
    {
        $this->signature = 'tenants:sync {--region='.config('services.region').' : The region to sync} {--limit=100 : How many at once}';

        parent::__construct();
    }

    public function handle(): int
    {
        return self::SUCCESS;
    }
}
