<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

$database = '/tmp/klartfoto-e2e.sqlite';

if (getenv('DB_CONNECTION') !== 'sqlite' || getenv('DB_DATABASE') !== $database) {
    fwrite(STDERR, "Refusing to prepare a database that is not the local E2E file.\n");
    exit(1);
}

if (is_file(__DIR__.'/../../bootstrap/cache/config.php')) {
    fwrite(STDERR, "Refusing to prepare the E2E database while configuration is cached.\n");
    exit(1);
}

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$resolved = config('database.connections.sqlite.database');

if (config('database.default') !== 'sqlite' || $resolved !== $database) {
    fwrite(STDERR, "Refusing to prepare unexpected database [{$resolved}].\n");
    exit(1);
}

if (is_file($database)) {
    unlink($database);
}

Artisan::call('migrate', ['--force' => true]);

User::factory()->create([
    'name' => 'E2E Foto',
    'email' => 'e2e-foto@klartfoto.test',
    'password' => 'secret-password',
]);

echo "prepared\n";
