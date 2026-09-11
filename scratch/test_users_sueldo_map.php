<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$userNominaMap = [];
$users = Illuminate\Support\Facades\DB::table('users')
    ->where('status', 0)
    ->get();

echo "=== USUARIOS ACTIVOS EN TABLA USERS (" . count($users) . " registros) ===\n";
foreach ($users->take(10) as $u) {
    $fullName = trim(preg_replace('/\s+/', ' ', "{$u->nombre} {$u->primer_apellido} {$u->segundo_apellido}"));
    echo "ID: {$u->id} | Nombre: {$fullName} | Nick: {$u->nick_name} | Sueldo: {$u->sueldo} | Status: {$u->status}\n";
}
