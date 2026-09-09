<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

$c = Config::get('database.connections.mysql');
$c['database'] = 'sistema_prendario_16';
Config::set('database.connections.test_p', $c);
DB::purge('test_p');

echo "=== Columnas de 'contratos' ===\n";
print_r(Schema::connection('test_p')->getColumnListing('contratos'));

echo "\n=== Tablas relacionadas con tipos de movimiento o contratos ===\n";
$tables = DB::connection('test_p')->select("SHOW TABLES");
foreach ($tables as $t) {
    $tName = array_values((array)$t)[0];
    if (str_contains($tName, 'mov') || str_contains($tName, 'tipo') || str_contains($tName, 'contra') || str_contains($tName, 'avaluo')) {
        echo " - {$tName}\n";
    }
}

$fechaInicio = '2026-09-01 00:00:00';
$fechaFin = '2026-09-09 23:59:59';

$catMovQuery = DB::connection('test_p')->select("
    SELECT 
        c.cod_usuario,
        u.nombre as valuador,
        COALESCE(tm.tipo_movimiento, 'EMPEÑO') as tipo_movimiento,
        CASE 
            WHEN c.cod_tipo_prenda = 2 THEN 'AUTOS'
            WHEN c.cod_tipo_prenda = 3 THEN 'VARIOS'
            WHEN c.cod_tipo_prenda = 1 AND (cat.categoria = 'PLATA' OR a.kilataje IN (925, 720, 800, 900, 500)) THEN 'PLATA'
            WHEN c.cod_tipo_prenda = 1 AND (cat.categoria = 'ORO' OR a.kilataje IN (8, 10, 12, 14, 18, 21, 22, 24)) THEN 'ORO'
            WHEN c.cod_tipo_prenda = 1 AND cat.categoria = 'ACERO' THEN 'VARIOS'
            WHEN c.cod_tipo_prenda = 1 THEN 'ORO'
            ELSE 'VARIOS'
        END as categoria_nombre,
        COUNT(c.cod_contrato) as cantidad_avaluos,
        SUM(c.avaluo) as monto_avaluo,
        SUM(c.prestamo) as monto_prestamo
    FROM contratos c
    INNER JOIN usuarios u ON c.cod_usuario = u.cod_usuario
    LEFT JOIN movimientos m ON c.cod_movimiento = m.cod_movimiento
    LEFT JOIN tipo_movimiento tm ON m.cod_tipo_movimiento = tm.cod_tipo_movimiento
    LEFT JOIN alhajas a ON c.cod_tipo_prenda = 1 AND c.cod_contrato = a.cod_contrato
    LEFT JOIN prendas p ON a.cod_prenda = p.cod_prenda
    LEFT JOIN categorias cat ON p.cod_categoria = cat.cod_categoria
    WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL AND c.activo = 1
    GROUP BY c.cod_usuario, u.nombre, tipo_movimiento, categoria_nombre
", [$fechaInicio, $fechaFin]);

echo "Resultados combinados (" . count($catMovQuery) . " filas):\n";
foreach ($catMovQuery as $row) {
    echo "Val: {$row->valuador} | Mov: {$row->tipo_movimiento} | Cat: {$row->categoria_nombre} | Pzas: {$row->cantidad_avaluos} | Aval: \${$row->monto_avaluo} | Prest: \${$row->monto_prestamo}\n";
}
exit;
