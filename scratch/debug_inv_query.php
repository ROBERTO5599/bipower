<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

$baseConfig = Config::get('database.connections.mysql');
$baseConfig['database'] = 'sistema_prendario_2';
Config::set('database.connections.sp2', $baseConfig);

$fechaInicio = '2026-10-01 00:00:00';
$fechaFin = '2026-10-22 23:59:59';

echo "=== CHECKING DB 2 DIRECTLY ===\n";

// 1. Check alhajas with p_venta in October 2026
$alhInOct = DB::connection('sp2')->select("
    SELECT COUNT(*) as c, MIN(p_venta) as min_pv, MAX(p_venta) as max_pv
    FROM alhajas 
    WHERE p_venta BETWEEN ? AND ?
", [$fechaInicio, $fechaFin]);
echo "alhajas with p_venta in range: " . $alhInOct[0]->c . " (min: {$alhInOct[0]->min_pv}, max: {$alhInOct[0]->max_pv})\n";

// 2. Check historico with cod_estatus_prenda = 9 in October 2026
$histInOct = DB::connection('sp2')->select("
    SELECT COUNT(*) as c, MIN(f_movimiento) as min_f, MAX(f_movimiento) as max_f
    FROM historico_articulos 
    WHERE cod_estatus_prenda = 9 AND f_movimiento BETWEEN ? AND ?
", [$fechaInicio, $fechaFin]);
echo "historico with cod 9 in range: " . $histInOct[0]->c . " (min: {$histInOct[0]->min_f}, max: {$histInOct[0]->max_f})\n";

// 3. Check what happens with the JOIN in InventarioRematadoController
$testJoin = DB::connection('sp2')->select("
    SELECT COUNT(*) as c
    FROM (
        SELECT h.cod_prenda, h.prestamo, h.precio, h.avaluo, h.f_movimiento, h.cod_contrato,
               ROW_NUMBER() OVER (PARTITION BY h.cod_prenda ORDER BY h.f_movimiento DESC, h.id_historico DESC) as rn
        FROM historico_articulos h
        WHERE h.cod_tipo_prenda = 1 
          AND (h.cod_estatus_prenda = 9 OR h.comentario LIKE '%Pase a Piso de Venta%')
    ) h
    INNER JOIN alhajas a ON a.cod_alhaja = h.cod_prenda
    WHERE h.rn = 1 
      AND COALESCE(a.p_venta, h.f_movimiento) BETWEEN ? AND ?
", [$fechaInicio, $fechaFin]);
echo "Test join alhajas count: " . $testJoin[0]->c . "\n";

// 4. Sample alhajas with p_venta
$sampleAlh = DB::connection('sp2')->select("
    SELECT cod_alhaja, cod_prenda, cod_contrato, prestamo, precio, p_venta, cod_estatus_prenda
    FROM alhajas 
    WHERE p_venta BETWEEN ? AND ?
    LIMIT 5
", [$fechaInicio, $fechaFin]);
echo "Sample alhajas:\n";
print_r($sampleAlh);

if (!empty($sampleAlh)) {
    $firstPrenda = $sampleAlh[0]->cod_prenda;
    $firstAlhaja = $sampleAlh[0]->cod_alhaja;
    echo "Checking historico for cod_prenda = $firstPrenda (or cod_alhaja = $firstAlhaja):\n";
    $histPrenda = DB::connection('sp2')->select("
        SELECT id_historico, cod_tipo_prenda, cod_prenda, id_articulo, cod_estatus_prenda, f_movimiento, comentario
        FROM historico_articulos
        WHERE cod_prenda IN (?, ?) OR id_articulo IN (?, ?)
    ", [$firstPrenda, $firstAlhaja, $firstPrenda, $firstAlhaja]);
    print_r($histPrenda);
}
