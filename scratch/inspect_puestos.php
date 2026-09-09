<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

echo "Columns in usuarios_sucursales_vm: " . implode(', ', Schema::getColumnListing('usuarios_sucursales_vm')) . PHP_EOL;
print_r(DB::table('usuarios_sucursales_vm')->limit(10)->get());

echo "\nColumns in puestos: " . implode(', ', Schema::getColumnListing('puestos')) . PHP_EOL;
print_r(DB::table('puestos')->get());
