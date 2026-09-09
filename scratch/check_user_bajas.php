<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('users')
    ->select('id', 'nombre', 'primer_apellido', 'nick_name', 'sucursal_id', 'status', 'fecha_baja')
    ->whereNull('fecha_baja')
    ->limit(10)
    ->get();
print_r($rows);

echo "Total users whereNull('fecha_baja'): " . DB::table('users')->whereNull('fecha_baja')->count() . PHP_EOL;
echo "Total users status = 0: " . DB::table('users')->where('status', 0)->count() . PHP_EOL;
echo "Total users status = 1: " . DB::table('users')->where('status', 1)->count() . PHP_EOL;
echo "Total users status = 0 AND whereNull('fecha_baja'): " . DB::table('users')->where('status', 0)->whereNull('fecha_baja')->count() . PHP_EOL;
echo "Total users status = 1 AND whereNull('fecha_baja'): " . DB::table('users')->where('status', 1)->whereNull('fecha_baja')->count() . PHP_EOL;

// Check which status corresponds to active
$bajaCountStatus0 = DB::table('users')->where('status', 0)->whereNotNull('fecha_baja')->count();
$bajaCountStatus1 = DB::table('users')->where('status', 1)->whereNotNull('fecha_baja')->count();
echo "status=0 with fecha_baja: $bajaCountStatus0\n";
echo "status=1 with fecha_baja: $bajaCountStatus1\n";
