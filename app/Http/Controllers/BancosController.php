<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class BancosController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::svm()->get();

        return view('bancos.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        
        $sucursales = Sucursal::svm()->get();
        $sucursalId = $request->input('sucursal_id');
        $sucursalesSeleccionadas = $sucursalId
            ? $sucursales->where('id_valora_mas', $sucursalId)
            : $sucursales;

        $baseConfig = Config::get('database.connections.mysql');
        
        $saldoTotalBancos = 0; 
        $saldoEfectivo = 0;
        
        $totalEntradas = 0;
        $totalSalidas = 0;

        $globalEntradasMap = [];
        $globalSalidasMap = [];
        $bancosCuentasMap = [];
        $dailySaldosMap = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'bancos_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // 1. Entradas por origen (Requerimiento 82)
                $entradasQ = DB::connection($connectionName)->select("
                    SELECT 
                        COALESCE(tm.tipo_movimiento, 'OTRO INGRESO') AS concepto,
                        m.cod_tipo_movimiento,
                        SUM(m.monto_efectivo) AS efectivo,
                        SUM(m.monto_tarjeta) AS tarjeta
                    FROM movimientos m
                    LEFT JOIN tipo_movimiento tm ON m.cod_tipo_movimiento = tm.cod_tipo_movimiento
                    WHERE m.f_alta BETWEEN ? AND ? 
                      AND m.f_cancela IS NULL
                      AND m.cod_tipo_movimiento NOT IN (1, 14, 18, 24) -- Excluir empeños y egresos de entradas
                    GROUP BY tm.tipo_movimiento, m.cod_tipo_movimiento
                ", [$fechaInicio, $fechaFin]);

                foreach ($entradasQ as $e) {
                    $key = strtoupper(trim($e->concepto));
                    $ef = (float) $e->efectivo;
                    $tar = (float) $e->tarjeta;
                    $subTot = $ef + $tar;

                    if ($subTot <= 0) continue;

                    if (!isset($globalEntradasMap[$key])) {
                        $globalEntradasMap[$key] = [
                            'concepto' => $key,
                            'efectivo' => 0,
                            'tarjeta' => 0,
                            'total' => 0
                        ];
                    }
                    $globalEntradasMap[$key]['efectivo'] += $ef;
                    $globalEntradasMap[$key]['tarjeta'] += $tar;
                    $globalEntradasMap[$key]['total'] += $subTot;

                    $totalEntradas += $subTot;
                    $saldoEfectivo += $ef;
                    $saldoTotalBancos += $tar;
                }

                // 2. Salidas por tipo (Requerimiento 83)
                // a) Inversión en Empeños Nuevos
                $empenosQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(prestamo), 0) as total FROM contratos WHERE f_contrato BETWEEN ? AND ? AND f_cancelacion IS NULL
                ", [$fechaInicio, $fechaFin]);
                $montoEmpenos = (float) ($empenosQ->total ?? 0);
                if ($montoEmpenos > 0) {
                    $keyEmp = 'INVERSIÓN EN EMPEÑOS NUEVOS';
                    if (!isset($globalSalidasMap[$keyEmp])) {
                        $globalSalidasMap[$keyEmp] = ['concepto' => $keyEmp, 'efectivo' => 0, 'tarjeta' => 0, 'total' => 0];
                    }
                    $globalSalidasMap[$keyEmp]['efectivo'] += $montoEmpenos;
                    $globalSalidasMap[$keyEmp]['total'] += $montoEmpenos;
                    $totalSalidas += $montoEmpenos;
                }

                // b) Gastos Operativos y Proveedores
                $gastosQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(COALESCE(autorizado, solicitado, 0)), 0) as total FROM gastos 
                    WHERE COALESCE(f_aplicacion, f_autorizado, f_solicitado) BETWEEN ? AND ? AND f_cancelacion IS NULL AND activo = 1
                ", [$fechaInicio, $fechaFin]);
                $montoGastos = (float) ($gastosQ->total ?? 0);
                if ($montoGastos > 0) {
                    $keyGasto = 'GASTOS OPERATIVOS Y PROVEEDORES';
                    if (!isset($globalSalidasMap[$keyGasto])) {
                        $globalSalidasMap[$keyGasto] = ['concepto' => $keyGasto, 'efectivo' => 0, 'tarjeta' => 0, 'total' => 0];
                    }
                    $globalSalidasMap[$keyGasto]['efectivo'] += $montoGastos;
                    $globalSalidasMap[$keyGasto]['total'] += $montoGastos;
                    $totalSalidas += $montoGastos;
                }

                // c) Demás salidas en movimientos (Retiros, etc.)
                $salidasMovQ = DB::connection($connectionName)->select("
                    SELECT 
                        COALESCE(tm.tipo_movimiento, 'OTRA SALIDA') AS concepto,
                        SUM(m.monto_efectivo) AS efectivo,
                        SUM(m.monto_tarjeta) AS tarjeta
                    FROM movimientos m
                    LEFT JOIN tipo_movimiento tm ON m.cod_tipo_movimiento = tm.cod_tipo_movimiento
                    WHERE m.f_alta BETWEEN ? AND ? 
                      AND m.f_cancela IS NULL
                      AND m.cod_tipo_movimiento IN (14, 18, 24)
                    GROUP BY tm.tipo_movimiento
                ", [$fechaInicio, $fechaFin]);

                foreach ($salidasMovQ as $sMov) {
                    $keyS = strtoupper(trim($sMov->concepto));
                    $efS = (float) $sMov->efectivo;
                    $tarS = (float) $sMov->tarjeta;
                    $subS = $efS + $tarS;

                    if ($subS <= 0 || $keyS === 'GASTOS') continue;

                    if (!isset($globalSalidasMap[$keyS])) {
                        $globalSalidasMap[$keyS] = ['concepto' => $keyS, 'efectivo' => 0, 'tarjeta' => 0, 'total' => 0];
                    }
                    $globalSalidasMap[$keyS]['efectivo'] += $efS;
                    $globalSalidasMap[$keyS]['tarjeta'] += $tarS;
                    $globalSalidasMap[$keyS]['total'] += $subS;
                    $totalSalidas += $subS;
                }

                // 3. Comisiones bancarias por cuenta (Requerimiento 84)
                $bancosQ = DB::connection($connectionName)->select("
                    SELECT 
                        COALESCE(b.nom_banco, 'TERMINAL TPV') as banco_nombre,
                        COALESCE(t.nombre_titular, 'CUENTA GENERAL') as titular,
                        COALESCE(t.num_tarjeta, '****') as num_cuenta,
                        COUNT(m.cod_movimiento) as transacciones,
                        SUM(m.monto_tarjeta) as total_tarjeta
                    FROM movimientos m
                    LEFT JOIN tarjetas t ON m.id_tarjeta = t.id_tarjeta
                    LEFT JOIN bancos b ON t.id_banco = b.id_banco
                    WHERE m.f_alta BETWEEN ? AND ? AND m.f_cancela IS NULL AND m.monto_tarjeta > 0
                    GROUP BY b.nom_banco, t.nombre_titular, t.num_tarjeta
                ", [$fechaInicio, $fechaFin]);

                foreach ($bancosQ as $bq) {
                    $bName = strtoupper(trim($bq->banco_nombre));
                    $titularStr = trim($bq->titular);
                    $numCtaRaw = trim($bq->num_cuenta);
                    $montoTar = (float) $bq->total_tarjeta;
                    $cnt = (int) $bq->transacciones;

                    // Desencriptar número de cuenta/tarjeta para obtener los verdaderos últimos 4 dígitos
                    $decryptedNo = $this->decryptCardNumber($numCtaRaw);
                    $last4 = !empty($decryptedNo)
                        ? (strlen($decryptedNo) > 4 ? substr($decryptedNo, -4) : $decryptedNo)
                        : (!empty($numCtaRaw) && $numCtaRaw !== '****' ? (strlen($numCtaRaw) > 4 ? substr($numCtaRaw, -4) : $numCtaRaw) : '');

                    $cuentaFormatted = !empty($last4) ? "{$titularStr} (****{$last4})" : "{$titularStr} (****)";

                    // Comisión estimada: 2.99% + $1.00 base por trx + 16% IVA
                    $comisionBase = round(($montoTar * 0.0299) + ($cnt * 1.00), 2);
                    $ivaCom = round($comisionBase * 0.16, 2);
                    $comisionTotal = $comisionBase + $ivaCom;

                    if (!isset($bancosCuentasMap[$bName])) {
                        $bancosCuentasMap[$bName] = [
                            'banco' => $bName,
                            'sucursal' => $sucursal->nombre,
                            'cuenta' => $cuentaFormatted,
                            'transacciones' => 0,
                            'monto_tarjeta' => 0,
                            'comision_total' => 0,
                            'neto_depositado' => 0
                        ];
                    }
                    $bancosCuentasMap[$bName]['transacciones'] += $cnt;
                    $bancosCuentasMap[$bName]['monto_tarjeta'] += $montoTar;
                    $bancosCuentasMap[$bName]['comision_total'] += $comisionTotal;
                    $bancosCuentasMap[$bName]['neto_depositado'] += ($montoTar - $comisionTotal);
                }

                // 4. Diario para evolución en el tiempo por cuenta / banco (Requerimiento 79 & 80)
                $dailyQ = DB::connection($connectionName)->select("
                    SELECT 
                        DATE(m.f_alta) as fecha,
                        COALESCE(b.nom_banco, 'BANCO GENERAL') as banco_nombre,
                        SUM(m.monto_tarjeta) as total_tarjeta,
                        SUM(m.monto_efectivo) as total_efectivo
                    FROM movimientos m
                    LEFT JOIN tarjetas t ON m.id_tarjeta = t.id_tarjeta
                    LEFT JOIN bancos b ON t.id_banco = b.id_banco
                    WHERE m.f_alta BETWEEN ? AND ? AND m.f_cancela IS NULL
                    GROUP BY DATE(m.f_alta), b.nom_banco
                    ORDER BY DATE(m.f_alta) ASC
                ", [$fechaInicio, $fechaFin]);

                foreach ($dailyQ as $dq) {
                    $f = $dq->fecha;
                    $b = strtoupper(trim($dq->banco_nombre));
                    $netoD = (float)$dq->total_tarjeta;

                    if (!isset($dailySaldosMap[$f][$b])) {
                        $dailySaldosMap[$f][$b] = 0;
                    }
                    $dailySaldosMap[$f][$b] += $netoD;
                }

            } catch (\Exception $e) {
                Log::error("Error Bancos en {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // Procesar Entradas por origen con porcentajes
        $entradasList = array_values($globalEntradasMap);
        usort($entradasList, fn($a, $b) => $b['total'] <=> $a['total']);
        foreach ($entradasList as &$eRow) {
            $eRow['pct_total'] = $totalEntradas > 0 ? round(($eRow['total'] / $totalEntradas) * 100, 1) : 0;
        }
        unset($eRow);

        // Procesar Salidas por tipo con porcentajes
        $salidasList = array_values($globalSalidasMap);
        usort($salidasList, fn($a, $b) => $b['total'] <=> $a['total']);
        foreach ($salidasList as &$sRow) {
            $sRow['pct_total'] = $totalSalidas > 0 ? round(($sRow['total'] / $totalSalidas) * 100, 1) : 0;
        }
        unset($sRow);

        // Procesar Cuentas / Comisiones
        $cuentasList = array_values($bancosCuentasMap);
        usort($cuentasList, fn($a, $b) => $b['monto_tarjeta'] <=> $a['monto_tarjeta']);

        $comisionesTotales = array_sum(array_column($cuentasList, 'comision_total'));
        $montoTarjetaTotal = array_sum(array_column($cuentasList, 'monto_tarjeta'));
        $tasaComisionPromedio = $montoTarjetaTotal > 0 ? round(($comisionesTotales / $montoTarjetaTotal) * 100, 2) : 0;

        // Requerimiento 80: Saldo mínimo, máximo y promedio por mes/período
        $flujoNetoMensual = $totalEntradas - $totalSalidas;
        $dailyTotals = [];
        foreach ($dailySaldosMap as $date => $bancos) {
            $dailyTotals[$date] = array_sum($bancos);
        }
        $saldoMinimo = count($dailyTotals) > 0 ? min($dailyTotals) : 0;
        $saldoMaximo = count($dailyTotals) > 0 ? max($dailyTotals) : 0;
        $saldoPromedio = count($dailyTotals) > 0 ? array_sum($dailyTotals) / count($dailyTotals) : 0;

        // Requerimiento 79: Chart Multi-línea de evolución por banco / cuenta
        $datesList = array_keys($dailySaldosMap);
        sort($datesList);

        $bankNames = [];
        foreach ($dailySaldosMap as $date => $bancos) {
            foreach ($bancos as $bName => $val) {
                if (!in_array($bName, $bankNames)) {
                    $bankNames[] = $bName;
                }
            }
        }

        $datasetsEvolucion = [];
        $colorPalette = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997'];
        $colorIdx = 0;

        foreach ($bankNames as $bName) {
            $seriesData = [];
            foreach ($datesList as $date) {
                $seriesData[] = round($dailySaldosMap[$date][$bName] ?? 0, 2);
            }
            $datasetsEvolucion[] = [
                'label' => $bName,
                'data' => $seriesData,
                'borderColor' => $colorPalette[$colorIdx % count($colorPalette)],
                'backgroundColor' => 'transparent',
                'tension' => 0.2
            ];
            $colorIdx++;
        }

        $chartEvolucionSaldos = [
            'labels' => array_map(fn($d) => date('d M', strtotime($d)), $datesList),
            'datasets' => $datasetsEvolucion
        ];

        // Chart Flujos Mensuales
        $chartFlujosMensuales = [
            'labels' => ['Entradas Totales', 'Salidas Totales', 'Flujo Libre Neto'],
            'data' => [round($totalEntradas, 2), round($totalSalidas, 2), round($flujoNetoMensual, 2)]
        ];

        // Chart Entradas por Origen Top 5
        $topEntradas = array_slice($entradasList, 0, 5);
        $chartEntradasPorOrigen = [
            'labels' => array_map(fn($item) => $item['concepto'], $topEntradas),
            'data' => array_map(fn($item) => round($item['total'], 2), $topEntradas)
        ];

        // Chart Salidas por Tipo
        $chartSalidasPorTipo = [
            'labels' => array_map(fn($item) => $item['concepto'], $salidasList),
            'data' => array_map(fn($item) => round($item['total'], 2), $salidasList)
        ];

        return response()->json([
            'saldoTotalBancos' => $saldoTotalBancos,
            'saldoEfectivo' => $saldoEfectivo,
            'flujoNetoMensual' => $flujoNetoMensual,
            'totalEntradas' => $totalEntradas,
            'totalSalidas' => $totalSalidas,

            // Req 80
            'saldoMinimo' => round($saldoMinimo, 2),
            'saldoMaximo' => round($saldoMaximo, 2),
            'saldoPromedio' => round($saldoPromedio, 2),

            // Req 84
            'comisionesTotales' => round($comisionesTotales, 2),
            'tasaComisionPromedio' => $tasaComisionPromedio,
            'detalleCuentas' => $cuentasList,

            // Req 79
            'chartEvolucionSaldos' => $chartEvolucionSaldos,

            // Req 82 & 83
            'entradasPorOrigen' => $entradasList,
            'salidasPorTipo' => $salidasList,

            // Charts
            'chartFlujosMensuales' => $chartFlujosMensuales,
            'chartEntradasPorOrigen' => $chartEntradasPorOrigen,
            'chartSalidasPorTipo' => $chartSalidasPorTipo
        ]);
    }

    private function decryptCardNumber($encrypted)
    {
        if (empty($encrypted)) {
            return '';
        }
        try {
            $keyBase = 'ClaveSeguraParaMiAplicacion';
            $ivBase = 'VectorInicialParaAES';
            $key = hash('sha256', $keyBase, true);
            $iv = md5($ivBase, true);
            $decrypted = openssl_decrypt(base64_decode($encrypted), 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
            return $decrypted ?: '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
