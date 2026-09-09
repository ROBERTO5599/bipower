<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Compare created_at or updated_at or sample of status=0 vs status=1
echo "=== Sample status = 0 (Null fecha_baja) ===\n";
$s0 = DB::table('users')->where('status', 0)->whereNull('fecha_baja')->limit(5)->get(['id', 'nombre', 'primer_apellido', 'puesto_id', 'fecha_ingreso', 'status', 'created_at', 'updated_at']);
print_r($s0);

echo "=== Sample status = 1 (Null fecha_baja) ===\n";
$s1 = DB::table('users')->where('status', 1)->whereNull('fecha_baja')->limit(5)->get(['id', 'nombre', 'primer_apellido', 'puesto_id', 'fecha_ingreso', 'status', 'created_at', 'updated_at']);
print_r($s1);
