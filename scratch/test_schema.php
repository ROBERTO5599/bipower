<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$categories = ['MERCANCIA GENERAL', 'ORO', 'PLATA', 'AUTOS'];
$standardIndicators = [
    "INVENTARIO", "Empeño", "Refrendos", "Desempeño", "Ventas",
    "Ventas Directas", "Apartados Liquidados",
    "Intereses", "Remate", "Bazar",
    "Utilidad del Mes Por venta", "Interés + Util Vta",
    "Gastos del Mes", "Utilidad Neta del Mes",
    "Créditos Vigentes",
    "Créditos Colocados",
    "Pago Crédito/Abono a Créditos",
    "Liquidación de Créditos",
    "Utilidad del Crédito",
    "Crédito Vencido Mensual - Préstamo",
    "Crédito Vencido Mensual - Cantidad Pagada",
    "Crédito Vencido Mensual - Adeudo",
    "Crédito Vencido Global - Préstamo",
    "Crédito Vencido Global - Cantidad Pagada",
    "Crédito Vencido Global - Adeudo",
    "Devoluciones",                 
    "Garantías - Ventas",               
    "Garantías - Apartados Liquidados",
    "Garantías - Enganche Crédito",
];

function matchMetaTest($dbIndicator, $standardIndicator) {
    $unwanted = [
        'Á'=>'A', 'À'=>'A', 'Â'=>'A', 'Ä'=>'A', 'Ã'=>'A', 'Å'=>'A', 'á'=>'a', 'à'=>'a', 'â'=>'a', 'ä'=>'a', 'ã'=>'a', 'å'=>'a',
        'É'=>'E', 'È'=>'E', 'Ê'=>'E', 'Ë'=>'E', 'é'=>'e', 'è'=>'e', 'ê'=>'e', 'ë'=>'e',
        'Í'=>'I', 'Ì'=>'I', 'Î'=>'I', 'Ï'=>'I', 'í'=>'i', 'ì'=>'i', 'î'=>'i', 'ï'=>'i',
        'Ó'=>'O', 'Ò'=>'O', 'Ô'=>'O', 'Ö'=>'O', 'Õ'=>'O', 'ó'=>'o', 'ò'=>'o', 'ô'=>'o', 'ö'=>'o', 'õ'=>'o',
        'Ú'=>'U', 'Ù'=>'U', 'Û'=>'U', 'Ü'=>'U', 'ú'=>'u', 'ù'=>'u', 'û'=>'u', 'ü'=>'u',
        'Ñ'=>'N', 'ñ'=>'n', 'Ç'=>'C', 'ç'=>'c'
    ];
    $dbInd = strtolower(strtr(trim($dbIndicator ?? ''), $unwanted));
    $stdInd = strtolower(strtr(trim($standardIndicator ?? ''), $unwanted));
    
    if ($dbInd === $stdInd) return true;

    $cleanDb = preg_replace('/\s+/', ' ', trim(preg_replace('/[()\-]/', ' ', $dbInd)));
    $cleanStd = preg_replace('/\s+/', ' ', trim(preg_replace('/[()\-]/', ' ', $stdInd)));

    if ($cleanDb === $cleanStd) return true;

    if (strpos($stdInd, 'utilidad del mes por ven') === 0 && strpos($dbInd, 'utilidad del mes por ven') === 0) return true;

    $aliases = [
        'creditos vigentes' => ['credito vigente', 'creditos vigentes', 'vigentes'],
        'creditos colocados' => ['credito colocado', 'creditos colocados', 'colocacion', 'creditos colocados colocacion'],
        'pago credito/abono a creditos' => ['pago a creditos', 'pago credito', 'pago a creditos cobranza', 'cobranza', 'abono a creditos', 'pago credito abono a creditos'],
        'liquidacion de creditos' => ['liquidacion creditos', 'liquidacion de creditos', 'liquidacion de credito'],
        'utilidad del credito' => ['utilidad credito', 'utilidad del credito'],
        'credito vencido mensual - prestamo' => ['credito vencido mensual prestamo'],
        'credito vencido mensual - cantidad pagada' => ['credito vencido mensual cobrado', 'credito vencido mensual cantidad pagada'],
        'credito vencido mensual - adeudo' => ['credito vencido mensual adeudo'],
        'credito vencido global - prestamo' => ['credito vencido global prestamo'],
        'credito vencido global - cantidad pagada' => ['credito vencido global cobrado', 'credito vencido global cantidad pagada'],
        'credito vencido global - adeudo' => ['credito vencido global adeudo'],
        'garantias - ventas' => ['garantias de ventas', 'garantias ventas'],
        'garantias - apartados liquidados' => ['garantias de apartados liquidados', 'garantias apartados liquidados'],
        'garantias - enganche credito' => ['garantias de enganche credito', 'garantias de enganche de credito', 'garantias enganche credito'],
    ];

    if (isset($aliases[$stdInd])) {
        foreach ($aliases[$stdInd] as $alias) {
            if ($cleanDb === $alias || strpos($cleanDb, $alias) !== false) {
                return true;
            }
        }
    }

    if (strpos($stdInd, ' - ') !== false) {
        $partes = explode(' - ', $stdInd, 2);
        $izquierda = trim($partes[0]);
        $derecha = trim($partes[1]);
        
        if (strpos($stdInd, 'garantias') === 0) {
            if (strpos($dbInd, 'garantias') === 0) {
                if ($dbInd === 'garantias - ' . $derecha || $cleanDb === 'garantias ' . $derecha || strpos($dbInd, $derecha) !== false) {
                    return true;
                }
            }
        } else {
            if ($dbInd === $izquierda || strpos($dbInd, $izquierda) !== false) {
                return true;
            }
        }
    }
    return false;
}

$dbIndicators = DB::table('resumen_operaciones_metas')->select('indicador')->distinct()->pluck('indicador');

echo "Checking mappings for distinct indicators in DB:\n";
foreach ($dbIndicators as $dbInd) {
    $matches = [];
    foreach ($standardIndicators as $stdInd) {
        if (matchMetaTest($dbInd, $stdInd)) {
            $matches[] = $stdInd;
        }
    }
    echo sprintf("DB Indicator '%-35s' => Matches: %s\n", $dbInd, implode(', ', $matches));
}
