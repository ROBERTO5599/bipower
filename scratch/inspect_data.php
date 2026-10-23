<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$baseConfig = Config::get('database.connections.mysql');

$availableDbs = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19, 22, 24];

foreach ($availableDbs as $dbId) {
    $dbName = 'sistema_prendario_' . $dbId;
    $conn = 'db_' . $dbId;
    $cfg = $baseConfig;
    $cfg['database'] = $dbName;
    Config::set("database.connections.$conn", $cfg);
    DB::purge($conn);

    try {
        // Check count of p_venta in alhajas
        $countPVentaAlh = DB::connection($conn)->selectOne("SELECT COUNT(*) as c FROM alhajas WHERE p_venta IS NOT NULL")->c;
        $maxPVentaAlh = DB::connection($conn)->selectOne("SELECT MAX(p_venta) as m, MIN(p_venta) as min_pv FROM alhajas WHERE p_venta IS NOT NULL");
        
        // Check historico_articulos
        $countHist9 = DB::connection($conn)->selectOne("SELECT COUNT(*) as c FROM historico_articulos WHERE cod_estatus_prenda = 9")->c;
        $maxHist9 = DB::connection($conn)->selectOne("SELECT MAX(f_movimiento) as m FROM historico_articulos WHERE cod_estatus_prenda = 9");

        echo "DB $dbId ($dbName): alhajas p_venta count: $countPVentaAlh (Max: {$maxPVentaAlh->m}, Min: {$maxPVentaAlh->min_pv}) | hist cod 9 count: $countHist9 (Max: {$maxHist9->m})\n";
    } catch (\Exception $e) {
        echo "DB $dbId Error: " . $e->getMessage() . "\n";
    }
}
