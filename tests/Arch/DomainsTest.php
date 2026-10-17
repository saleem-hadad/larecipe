<?php

/*
 * The Portable-Domain Rule: the domains a future standalone CLI needs keep
 * Laravel out of everything except their adapter folders (ARCHITECTURE.md 3.3).
 */

$adapters = fn (string $domain) => array_map(
    fn (string $folder) => "LaRecipe\\Domains\\{$domain}\\{$folder}",
    ['Laravel', 'Controllers', 'Requests', 'Commands', 'Models', 'Listeners', 'Mcp', "{$domain}ServiceProvider"],
);

foreach (['Content', 'Sources', 'Markdown', 'References', 'Access', 'Search', 'Validation', 'Agents', 'Sync', 'Upgrade', 'Export'] as $domain) {
    arch("the {$domain} domain stays portable outside its adapters")
        ->expect("LaRecipe\\Domains\\{$domain}")
        ->not->toUse(['Illuminate', 'Laravel'])
        ->ignoring($adapters($domain));
}

arch('domains never call global Laravel helpers')
    ->expect(['LaRecipe\Domains\Content', 'LaRecipe\Domains\Sources\Drivers', 'LaRecipe\Domains\Markdown'])
    ->not->toUse(['config', 'app', 'event', 'now', 'cache', 'request', 'auth']);
