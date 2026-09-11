<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$sucursales = App\Models\Sucursal::all();
echo "=== TODAS LAS SUCURSALES EN TABLA SUCURSALES (" . count($sucursales) . " registros) ===\n";
foreach ($sucursales as $s) {
    echo "ID: {$s->id} | Nombre: {$s->nombre} | Descripcion: {$s->descripcion} | ID ValoraMas: {$s->id_valora_mas}\n";
}
