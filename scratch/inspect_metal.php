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

$res = DB::connection('test_branch')->select("
    SELECT DISTINCT a.kilataje, cat.categoria, COUNT(*) as cnt
    FROM contratos c
    JOIN alhajas a ON c.cod_contrato = a.cod_contrato
    LEFT JOIN prendas p ON a.cod_prenda = p.cod_prenda
    LEFT JOIN categorias cat ON p.cod_categoria = cat.cod_categoria
    WHERE c.cod_tipo_prenda = 1
    GROUP BY a.kilataje, cat.categoria
    ORDER BY cat.categoria, a.kilataje
");
print_r($res);
