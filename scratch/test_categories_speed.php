<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

function normalizarCategoria($catRaw) {
    $cat = strtoupper(trim($catRaw ?? ''));
    if (empty($cat) || $cat === 'VARIOS' || $cat === 'ARTICULOS VARIOS') return 'VARIOS';
    if (str_contains($cat, 'HERRAMIENTA')) return 'HERRAMIENTAS';
    if (str_contains($cat, 'COMPTO') || str_contains($cat, 'COMPUTO')) return 'CÓMPUTO';
    if (str_contains($cat, 'CELULAR') || str_contains($cat, 'CELURARES')) return 'CELULARES';
    if (str_contains($cat, 'ELECTRODOMESTICO')) return 'ELECTRODOMÉSTICOS';
    if (str_contains($cat, 'ELECTRONIC')) return 'ELECTRÓNICOS';
    if (str_contains($cat, 'BICICLETA')) return 'BICICLETAS';
    if (str_contains($cat, 'INSTRUMENT')) return 'INSTRUMENTOS';
    if (str_contains($cat, 'PANTALLA') || str_contains($cat, 'TELEVISOR') || str_contains($cat, 'TV')) return 'PANTALLAS / TV';
    if (str_contains($cat, 'SONIDO') || str_contains($cat, 'AUDIO')) return 'AUDIO Y SONIDO';
    if (str_contains($cat, 'LINEA BLANCA')) return 'LÍNEA BLANCA';
    if (str_contains($cat, 'VIDEOJUEGO') || str_contains($cat, 'CONSOLA')) return 'CONSOLAS Y VIDEOJUEGOS';
    if (str_contains($cat, 'RELOJ')) return 'RELOJERÍA';
    if (str_contains($cat, 'CAMARA')) return 'FOTOGRAFÍA Y CÁMARAS';
    if ($cat === 'METAL' || str_contains($cat, 'ALHAJA') || str_contains($cat, 'ORO')) return 'ALHAJAS Y ORO';
    if (str_contains($cat, 'AUTO')) return 'AUTOMÓVILES';
    return $cat;
}

$baseConfig = Config::get('database.connections.mysql');
$baseConfig['database'] = 'sistema_prendario_2';
Config::set('database.connections.test_branch', $baseConfig);
DB::purge('test_branch');

$fechaInicio = '2024-01-01 00:00:00';
$fechaFin = '2025-12-31 23:59:59';

$catQ = DB::connection('test_branch')->select("
    SELECT 
        c.cod_usuario,
        c.cod_tipo_prenda,
        tp.tipo_prenda,
        COALESCE(cat.categoria, tp.tipo_prenda) as categoria_nombre,
        COUNT(c.cod_contrato) as cantidad_avaluos,
        SUM(c.avaluo) as monto_avaluo,
        SUM(c.prestamo) as monto_prestamo
    FROM contratos c
    LEFT JOIN tipo_prenda tp ON c.cod_tipo_prenda = tp.cod_tipo_prenda
    LEFT JOIN varios v ON c.cod_tipo_prenda = 3 AND c.cod_contrato = v.cod_contrato
    LEFT JOIN prendas p ON v.cod_prenda = p.cod_prenda
    LEFT JOIN categorias cat ON p.cod_categoria = cat.cod_categoria
    WHERE c.f_contrato BETWEEN ? AND ? AND c.f_cancelacion IS NULL AND c.activo = 1
    GROUP BY c.cod_usuario, c.cod_tipo_prenda, tp.tipo_prenda, COALESCE(cat.categoria, tp.tipo_prenda)
", [$fechaInicio, $fechaFin]);

$globalCats = [];
$byUser = [];
foreach ($catQ as $row) {
    $uid = $row->cod_usuario;
    $norm = normalizarCategoria($row->categoria_nombre);
    if (!isset($byUser[$uid][$norm])) {
        $byUser[$uid][$norm] = ['categoria' => $norm, 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0];
    }
    $byUser[$uid][$norm]['cantidad'] += (int)$row->cantidad_avaluos;
    $byUser[$uid][$norm]['monto_avaluo'] += (float)$row->monto_avaluo;
    $byUser[$uid][$norm]['monto_prestamo'] += (float)$row->monto_prestamo;

    if (!isset($globalCats[$norm])) {
        $globalCats[$norm] = ['categoria' => $norm, 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0];
    }
    $globalCats[$norm]['cantidad'] += (int)$row->cantidad_avaluos;
    $globalCats[$norm]['monto_avaluo'] += (float)$row->monto_avaluo;
    $globalCats[$norm]['monto_prestamo'] += (float)$row->monto_prestamo;
}

echo "=== Top Global Categories by Monto Avalúo ===\n";
usort($globalCats, function($a, $b) { return $b['monto_avaluo'] <=> $a['monto_avaluo']; });
foreach (array_slice($globalCats, 0, 8) as $g) {
    echo "{$g['categoria']}: {$g['cantidad']} piezas | Avalúo: $" . number_format($g['monto_avaluo'], 2) . " | Préstamo: $" . number_format($g['monto_prestamo'], 2) . "\n";
}

$sampleUid = array_keys($byUser)[1];
$userCats = array_values($byUser[$sampleUid]);
usort($userCats, function($a, $b) { return $b['monto_avaluo'] <=> $a['monto_avaluo']; });
echo "\n=== Sample User $sampleUid Categories ===\n";
foreach (array_slice($userCats, 0, 5) as $uc) {
    echo "{$uc['categoria']}: {$uc['cantidad']} avaluos | Monto: $" . number_format($uc['monto_avaluo'], 2) . "\n";
}
