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

$q = DB::connection('test_branch')->select("
    SELECT 
        c.cod_usuario,
        u.nombre as valuador,
        c.cod_tipo_prenda,
        tp.tipo_prenda,
        COALESCE(cat.categoria, tp.tipo_prenda) as categoria_nombre,
        COUNT(c.cod_contrato) as cantidad_avaluos,
        SUM(c.avaluo) as monto_avaluo,
        SUM(c.prestamo) as monto_prestamo
    FROM contratos c
    INNER JOIN usuarios u ON c.cod_usuario = u.cod_usuario
    LEFT JOIN tipo_prenda tp ON c.cod_tipo_prenda = tp.cod_tipo_prenda
    LEFT JOIN varios v ON c.cod_tipo_prenda = 3 AND c.cod_contrato = v.cod_contrato
    LEFT JOIN prendas p ON v.cod_prenda = p.cod_prenda
    LEFT JOIN categorias cat ON p.cod_categoria = cat.cod_categoria
    WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL AND c.activo = 1
    GROUP BY c.cod_usuario, u.nombre, c.cod_tipo_prenda, tp.tipo_prenda, COALESCE(cat.categoria, tp.tipo_prenda)
    ORDER BY u.nombre, monto_avaluo DESC
    LIMIT 20
", [$fechaInicio, $fechaFin]);

print_r($q);
