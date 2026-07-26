<?php

use Illuminate\Contracts\Console\Kernel;

putenv('APP_ENV=testing');
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = config('database.default');
$database = config('database.connections.sqlite.database');

if ($connection !== 'sqlite' || $database !== ':memory:') {
    fwrite(STDERR, "Conexión de pruebas no aislada.\n");
    exit(1);
}

echo "sqlite|:memory:\n";
