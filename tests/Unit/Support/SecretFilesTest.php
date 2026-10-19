<?php

/*
 * The one list of files LaRecipe never reads, shared by snippet includes and
 * the server-side docs agent.
 */

use LaRecipe\Support\SecretFiles;

it('lists the places secrets live', function (string $path) {
    expect(SecretFiles::reason($path))->not->toBeNull();
})->with([
    '.env', '.env.production', '.git/config', 'config/services.php', 'bootstrap/cache/config.php', 'storage/oauth-private.key',
    'vendor/acme/sdk/src/Client.php', 'node_modules/left-pad/index.js', 'auth.json', 'phpunit.xml', 'id_rsa', 'id_ed25519',
    'id_ed25519.pub', 'deploy.ppk', 'deploy_key', 'oauth-private', 'firebase-credentials.json', 'Firebase-Credentials.JSON',
    'database/database.sqlite', 'database/dump.sql', 'database/backups/users.json', 'dumps/orders.json', 'certs/server.pem',
]);

it('leaves ordinary code and docs alone', function (string $path) {
    expect(SecretFiles::reason($path))->toBeNull();
})->with([
    'app/Billing/Invoice.php', 'app/Http/Controllers/CredentialsController.php', 'app/Support/KeyRing.php', 'app/Monkey',
    'routes/api.php', 'composer.json', 'package.json', 'docs/3.x/index.md', 'README.md', 'database/migrations/2026_01_01_create_orders.php',
    'artisan', 'Dockerfile',
]);
