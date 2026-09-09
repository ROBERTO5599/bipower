<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Sucursal;

$totalNominaGlobal = (float) User::where('status', 0)->sum('sueldo');
$totalColabGlobal = (int) User::where('status', 0)->count();
$costoPromedio = $totalColabGlobal > 0 ? $totalNominaGlobal / $totalColabGlobal : 0;

echo "=== GLOBAL / CONSOLIDADO ===\n";
echo "Nómina Total (SUM(users.sueldo)): $" . number_format($totalNominaGlobal, 2) . PHP_EOL;
echo "Colaboradores Activos (status = 0): {$totalColabGlobal}\n";
echo "Costo Promedio por Colaborador: $" . number_format($costoPromedio, 2) . PHP_EOL;

echo "\n=== POR SUCURSAL (users.sueldo con sucursal_id = sucursales.id) ===\n";
$sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
foreach ($sucursales as $s) {
    $colabs = User::where('status', 0)->where('sucursal_id', $s->id)->count();
    $nomSuc = (float) User::where('status', 0)->where('sucursal_id', $s->id)->sum('sueldo');
    $promSuc = $colabs > 0 ? $nomSuc / $colabs : 0;
    echo "Sucursal '{$s->nombre}' (id: {$s->id}, id_valora_mas: {$s->id_valora_mas}):\n";
    echo "  - Colaboradores: {$colabs} | Nómina: $" . number_format($nomSuc, 2) . " | Promedio: $" . number_format($promSuc, 2) . PHP_EOL;
}
