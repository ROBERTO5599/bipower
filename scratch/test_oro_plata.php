<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$baseConfig = Config::get('database.connections.mysql');
$baseConfig['database'] = 'sistema_prendario_2';
Config::set('database.connections.test_branch', $baseConfig);
DB::purge('test_branch');

$fechaInicio = '2024-01-01 00:00:00';
$fechaFin = '2025-12-31 23:59:59';

$res = DB::connection('test_branch')->select("
    SELECT 
        CASE 
            WHEN c.cod_tipo_prenda = 2 THEN 'AUTOS'
            WHEN c.cod_tipo_prenda = 3 THEN 'VARIOS'
            WHEN c.cod_tipo_prenda = 1 AND (cat.categoria = 'PLATA' OR a.kilataje IN (925, 720, 800, 900, 500)) THEN 'PLATA'
            WHEN c.cod_tipo_prenda = 1 AND (cat.categoria = 'ORO' OR a.kilataje IN (8, 10, 12, 14, 18, 21, 22, 24)) THEN 'ORO'
            WHEN c.cod_tipo_prenda = 1 AND cat.categoria = 'ACERO' THEN 'VARIOS'
            WHEN c.cod_tipo_prenda = 1 THEN 'ORO'
            ELSE 'VARIOS'
        END as ramo,
        COUNT(c.cod_contrato) as cantidad_avaluos,
        SUM(c.avaluo) as monto_avaluo,
        SUM(c.prestamo) as monto_prestamo
    FROM contratos c
    LEFT JOIN alhajas a ON c.cod_tipo_prenda = 1 AND c.cod_contrato = a.cod_contrato
    LEFT JOIN prendas p ON a.cod_prenda = p.cod_prenda
    LEFT JOIN categorias cat ON p.cod_categoria = cat.cod_categoria
    WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL AND c.activo = 1
    GROUP BY ramo
    ORDER BY monto_avaluo DESC
", [$fechaInicio, $fechaFin]);

print_r($res);
