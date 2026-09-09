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

$sample = DB::connection('test_branch')->select("
    SELECT c.cod_usuario, u.nombre, COUNT(*) as total_contratos, 
           AVG(c.prestamo) as prom_prestamo, AVG(c.avaluo) as prom_avaluo,
           AVG(c.prestamo / NULLIF(c.avaluo, 0)) as ratio_prestamo_avaluo,
           AVG(c.venta / NULLIF(c.prestamo, 0)) as ratio_remate_prestamo
    FROM contratos c
    LEFT JOIN usuarios u ON c.cod_usuario = u.cod_usuario
    WHERE c.f_contrato >= '2024-01-01'
    GROUP BY c.cod_usuario, u.nombre
    ORDER BY total_contratos DESC
    LIMIT 10
");

echo "Sample valuadores:\n";
print_r($sample);
