<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$rows = DB::table('users')
    ->select('id', 'nombre', 'primer_apellido', 'nick_name', 'status', 'fecha_baja', 'comentario_baja', 'estatus_section')
    ->whereNotNull('fecha_baja')
    ->limit(10)
    ->get();
echo "Users with fecha_baja:\n";
print_r($rows);

$estatusSection = DB::table('users')
    ->select('estatus_section', DB::raw('count(*) as count'))
    ->groupBy('estatus_section')
    ->get();
echo "estatus_section counts:\n";
print_r($estatusSection);

$statusWithNullBaja = DB::table('users')
    ->whereNull('fecha_baja')
    ->select('status', 'estatus_section', DB::raw('count(*) as count'))
    ->groupBy('status', 'estatus_section')
    ->get();
echo "status with null fecha_baja:\n";
print_r($statusWithNullBaja);
