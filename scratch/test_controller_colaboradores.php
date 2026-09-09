<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$controller = new App\Http\Controllers\ColaboradoresController();
// Caso 1: Consolidado
echo "=== CASO 1: CONSOLIDADO (TODAS LAS SUCURSALES) ===\n";
$request = new Illuminate\Http\Request();
$response = $controller->data($request);
$data = json_decode($response->getContent(), true);

// Test query with combined movs and cats
$c = Config::get('database.connections.mysql');
$c['database'] = 'sistema_prendario_16';
Config::set('database.connections.test_p', $c);
DB::purge('test_p');

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
    WHERE c.f_contrato BETWEEN '2026-09-01 00:00:00' AND '2026-09-09 23:59:59' AND c.f_cancelacion IS NULL AND c.activo = 1
    GROUP BY c.cod_usuario, u.nombre, tm.tipo_movimiento, categoria_nombre
");

$branchValuadoresCats = [];
$branchValuadoresMovs = [];
$branchValuadoresCruzado = [];

foreach ($catMovQuery as $row) {
    $uid = $row->cod_usuario;
    $normCat = strtoupper(trim($row->categoria_nombre));
    $mov = strtoupper(trim($row->tipo_movimiento));

    // Por categoría
    if (!isset($branchValuadoresCats[$uid][$normCat])) {
        $branchValuadoresCats[$uid][$normCat] = ['categoria' => $normCat, 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0];
    }
    $branchValuadoresCats[$uid][$normCat]['cantidad'] += (int)$row->cantidad_avaluos;
    $branchValuadoresCats[$uid][$normCat]['monto_avaluo'] += (float)$row->monto_avaluo;
    $branchValuadoresCats[$uid][$normCat]['monto_prestamo'] += (float)$row->monto_prestamo;

    // Por tipo de movimiento
    if (!isset($branchValuadoresMovs[$uid][$mov])) {
        $branchValuadoresMovs[$uid][$mov] = ['tipo_movimiento' => $mov, 'cantidad' => 0, 'monto_avaluo' => 0, 'monto_prestamo' => 0];
    }
    $branchValuadoresMovs[$uid][$mov]['cantidad'] += (int)$row->cantidad_avaluos;
    $branchValuadoresMovs[$uid][$mov]['monto_avaluo'] += (float)$row->monto_avaluo;
    $branchValuadoresMovs[$uid][$mov]['monto_prestamo'] += (float)$row->monto_prestamo;

    // Cruzado
    $branchValuadoresCruzado[$uid][] = [
        'categoria' => $normCat,
        'tipo_movimiento' => $mov,
        'cantidad' => (int)$row->cantidad_avaluos,
        'monto_avaluo' => (float)$row->monto_avaluo,
        'monto_prestamo' => (float)$row->monto_prestamo,
    ];
}

echo "Ejemplo para usuario 262 (Cristina Elizabeth):\n";
echo "--- CATEGORIAS ---\n";
print_r($branchValuadoresCats[262] ?? []);
echo "--- TIPOS DE MOVIMIENTO ---\n";
print_r($branchValuadoresMovs[262] ?? []);
exit;

if (!empty($data['categoriasResumen'])) {
    echo "\n=== Top Global Categorias de Avaluos ===\n";
    foreach (array_slice($data['categoriasResumen'], 0, 6) as $cat) {
        echo "{$cat['categoria']}: {$cat['cantidad']} pzas | Monto Avalúo: $" . number_format($cat['monto_avaluo'], 2) . " | Monto Préstamo: $" . number_format($cat['monto_prestamo'], 2) . " ({$cat['pct_monto']}%)\n";
    }
}

if (!empty($data['valuadoresCalidad'])) {
    $firstVal = $data['valuadoresCalidad'][0];
    echo "\n=== Sample Valuador: {$firstVal['valuador']} ({$firstVal['sucursal']}) ===\n";
    echo "Total avalúos: {$firstVal['total_avaluos']} | Total prestado: $" . number_format($firstVal['total_prestado'], 2) . PHP_EOL;
    echo "Categorías desglosadas (" . count($firstVal['categorias'] ?? []) . "):\n";
    foreach ($firstVal['categorias'] as $c) {
        echo "  - {$c['categoria']}: {$c['cantidad']} pzas ({$c['pct_cantidad']}%) | Avalúo: $" . number_format($c['monto_avaluo'], 2) . " | Préstamo: $" . number_format($c['monto_prestamo'], 2) . PHP_EOL;
    }
}
