<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$baseConfig = Config::get('database.connections.mysql');
$baseConfig['database'] = 'sistema_prendario_2';
Config::set('database.connections.sp2', $baseConfig);

$colsHist = DB::connection('sp2')->select("SHOW COLUMNS FROM historico_articulos");
echo "--- historico_articulos columns ---\n";
foreach ($colsHist as $c) {
    echo $c->Field . " (" . $c->Type . ")\n";
}

$colsAlh = DB::connection('sp2')->select("SHOW COLUMNS FROM alhajas");
echo "--- alhajas columns with 'venta' or 'p_' ---\n";
foreach ($colsAlh as $c) {
    if (stripos($c->Field, 'venta') !== false || stripos($c->Field, 'p_') !== false || stripos($c->Field, 'remat') !== false) {
        echo $c->Field . " (" . $c->Type . ")\n";
    }
}
