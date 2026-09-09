<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

$statuses = DB::table('users')
    ->select('status', DB::raw('count(*) as count'))
    ->groupBy('status')
    ->get();
print_r($statuses);

$bajas = DB::table('users')
    ->select(DB::raw('fecha_baja IS NOT NULL as tiene_baja'), DB::raw('count(*) as count'))
    ->groupBy('tiene_baja')
    ->get();
print_r($bajas);

$statusVsBaja = DB::table('users')
    ->select('status', DB::raw('fecha_baja IS NOT NULL as tiene_baja'), DB::raw('count(*) as count'))
    ->groupBy('status', 'tiene_baja')
    ->get();
print_r($statusVsBaja);

// Check users by sucursal_id
$usersPorSucursal = DB::table('users')
    ->select('sucursal_id', DB::raw('count(*) as total'))
    ->groupBy('sucursal_id')
    ->get();
echo "\nUsers por sucursal_id:\n";
print_r($usersPorSucursal);
