<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "users whereNull(fecha_baja): " . User::whereNull('fecha_baja')->count() . PHP_EOL;
echo "users where status = 1 AND whereNull(fecha_baja): " . User::where('status', 1)->whereNull('fecha_baja')->count() . PHP_EOL;
echo "users where status = 0 AND whereNull(fecha_baja): " . User::where('status', 0)->whereNull('fecha_baja')->count() . PHP_EOL;

// Check users by puesto
$byPuesto = DB::table('users')
    ->leftJoin('puestos', 'users.puesto_id', '=', 'puestos.id')
    ->whereNull('users.fecha_baja')
    ->select('puestos.nombre as puesto', DB::raw('count(*) as count'))
    ->groupBy('puestos.nombre')
    ->get();
print_r($byPuesto);
