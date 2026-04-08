<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;
use Carbon\Carbon;

class MetasForecastController extends Controller
{
    public function index(Request $request)
    {
        $mesesHistorico = 12;
        $crecimiento = 5; // 5% por defeto
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('metas-forecast.index', compact('mesesHistorico', 'crecimiento', 'sucursales'));
    }

    public function data(Request $request)
    {
        $mesesHistorico = (int) $request->input('meses_historico', 12);
        $crecimientoPorcentaje = (float) $request->input('crecimiento', 5);
        $sucursalId = $request->input('sucursal_id');

        $crecimientoFactor = 1 + ($crecimientoPorcentaje / 100);
        
        // El forecast es usualmente para el mes actual o siguiente. Asumiremos mes actual como curso.
        $mesActual = Carbon::now();
        $mesObjetivo = $mesActual->month;
        $anioObjetivo = $mesActual->year;

        $fechaInicioHistorico = $mesActual->copy()->subMonths($mesesHistorico)->startOfMonth()->toDateString();
        // Usamos now()->endOfDay() para incluir lo actual del mes en los valores reales
        $fechaTopeActual = $mesActual->copy()->endOfDay()->toDateString(); 

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        if ($sucursalId) {
            $sucursales = $sucursales->where('id_valora_mas', $sucursalId);
        }

        $baseConfig = Config::get('database.connections.mysql');

        // Variables Globales Reales del Mes Curso
        $real_ventasTotales = 0;
        $real_empenosTotales = 0;
        $real_interesesTotales = 0;
        $real_utilidadOperativa = 0;

        // Metas Globales (Suma de Sucursales que ya incluyen override manual)
        $metaG_ventasTotales = 0;
        $metaG_empenosTotales = 0;
        $metaG_interesesTotales = 0;
        $metaG_utilidadOperativa = 0;

        // Histórico por mes global para la gráfica de línea
        $historiaMesesLabels = [];
        $historiaVentasAcumuladas = [];

        // Acumuladores para el cálculo global de Metas y Tendencias
        $global_history = []; // Estructura: [ "Y-m" => [ 'ventas' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0 ] ]
        
        // Estructura de métricas por sucursal para la tabla inferior
        $branchKPIs = [];

        foreach ($sucursales as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'forecast_dynamic_' . $sucursal->id_valora_mas;

            $branchData = [
                'real_ventas' => 0,
                'real_empenos' => 0,
                'real_intereses' => 0,
                'real_utilidad_operativa' => 0,
                'history' => []
            ];

            try {
                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.{$connectionName}", $config);
                DB::purge($connectionName);

                // 1. EXTRAER VENTAS HISTORICAS (y utilidades de venta)
                $ventasHist = DB::connection($connectionName)->select("
                    SELECT 
                        YEAR(ve.f_venta) as anio,
                        MONTH(ve.f_venta) as mes,
                        SUM(dv.venta10) as total_venta,
                        SUM(CASE 
                            WHEN ve.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                            WHEN ve.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                            WHEN ve.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                            ELSE 0
                        END) as prestamo_base
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    LEFT JOIN alhajas al ON ve.cod_tipo_prenda = 1 AND al.cod_alhaja = dv.cod_prenda
                    LEFT JOIN autos au ON ve.cod_tipo_prenda = 2 AND au.cod_auto = dv.cod_prenda
                    LEFT JOIN varios va ON ve.cod_tipo_prenda = 3 AND va.cod_varios = dv.cod_prenda
                    WHERE ve.f_cancela IS NULL
                    AND CAST(ve.f_venta AS DATE) BETWEEN ? AND ?
                    GROUP BY YEAR(ve.f_venta), MONTH(ve.f_venta)
                ", [$fechaInicioHistorico, $fechaTopeActual]);

                foreach ($ventasHist as $vh) {
                    $key = sprintf("%04d-%02d", $vh->anio, $vh->mes);
                    if (!isset($branchData['history'][$key])) {
                        $branchData['history'][$key] = ['ventas' => 0, 'utilidad_vta' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0];
                    }
                    $branchData['history'][$key]['ventas'] += (float)$vh->total_venta;
                    $branchData['history'][$key]['utilidad_vta'] += ((float)$vh->total_venta - (float)$vh->prestamo_base);

                    if ($vh->anio == $anioObjetivo && $vh->mes == $mesObjetivo) {
                        $branchData['real_ventas'] += (float)$vh->total_venta;
                    }
                }

                // 2. EXTRAER EMPEÑOS E INTERESES HISTORICOS (Movimientos)
                $movsHist = DB::connection($connectionName)->select("
                    SELECT 
                        YEAR(mo.f_alta) as anio,
                        MONTH(mo.f_alta) as mes,
                        SUM(CASE WHEN mo.cod_tipo_movimiento = 1 THEN mo.monto10 ELSE 0 END) as empenos,
                        SUM(CASE 
                            WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - COALESCE(con.prestamo, 0))
                            WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - COALESCE(ca.abono, 0))
                            ELSE 0
                        END) as intereses
                    FROM movimientos mo
                    LEFT JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                    WHERE mo.f_cancela IS NULL AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)
                    AND CAST(mo.f_alta AS DATE) BETWEEN ? AND ?
                    GROUP BY YEAR(mo.f_alta), MONTH(mo.f_alta)
                ", [$fechaInicioHistorico, $fechaTopeActual]);

                foreach ($movsHist as $mh) {
                    $key = sprintf("%04d-%02d", $mh->anio, $mh->mes);
                    if (!isset($branchData['history'][$key])) {
                        $branchData['history'][$key] = ['ventas' => 0, 'utilidad_vta' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0];
                    }
                    $empenoVal = (float)$mh->empenos;
                    $interesVal = (float)$mh->intereses;

                    $branchData['history'][$key]['empenos'] += $empenoVal;
                    $branchData['history'][$key]['intereses'] += $interesVal;

                    if ($mh->anio == $anioObjetivo && $mh->mes == $mesObjetivo) {
                        $branchData['real_empenos'] += $empenoVal;
                        $branchData['real_intereses'] += $interesVal;
                    }
                }

                // 3. EXTRAER GASTOS (Para Utilidad Operativa)
                $gastosHist = DB::connection($connectionName)->select("
                    SELECT 
                        YEAR(gas.f_solicitado) as anio,
                        MONTH(gas.f_solicitado) as mes,
                        SUM(gas.solicitado) as total_gasto
                    FROM gastos gas
                    WHERE gas.activo = 1 AND gas.cod_estatus = 2
                    AND CAST(gas.f_solicitado AS DATE) BETWEEN ? AND ?
                    GROUP BY YEAR(gas.f_solicitado), MONTH(gas.f_solicitado)
                ", [$fechaInicioHistorico, $fechaTopeActual]);

                $gastoActual = 0;
                foreach ($gastosHist as $gh) {
                    $key = sprintf("%04d-%02d", $gh->anio, $gh->mes);
                    if (!isset($branchData['history'][$key])) {
                        $branchData['history'][$key] = ['ventas' => 0, 'utilidad_vta' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0];
                    }
                    $branchData['history'][$key]['gastos'] += (float)$gh->total_gasto;

                    if ($gh->anio == $anioObjetivo && $gh->mes == $mesObjetivo) {
                        $gastoActual += (float)$gh->total_gasto;
                    }
                }

            // Consolidar Real Actual Utilidad Operativa
                $keyActual = sprintf("%04d-%02d", $anioObjetivo, $mesObjetivo);
                $utilidadActualVta = $branchData['history'][$keyActual]['utilidad_vta'] ?? 0;
                $branchData['real_utilidad_operativa'] = ($utilidadActualVta + $branchData['real_intereses']) - $gastoActual;

                // ============================================
                // CÁLCULO DE METAS ESTADÍSTICAS AUTOMÁTICAS
                // ============================================
                $objVentas = $this->calcularMeta($branchData['history'], 'ventas', $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);
                $objEmpenos = $this->calcularMeta($branchData['history'], 'empenos', $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);
                $objIntereses = $this->calcularMeta($branchData['history'], 'intereses', $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);
                $objUtilidadOperativa = $this->calcularMetaUtilidad($branchData['history'], $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);

                // ============================================
                // BÚSQUEDA DE METAS MANUALES (Sobreescritura)
                // ============================================
                $isManualVentas = false;
                $isManualEmpenos = false;
                $isManualIntereses = false;
                $isManualUtilidad = false;

                try {
                    // Intento buscar en la tabla "metas" descubierta
                    $metasManuales = DB::connection($connectionName)->select("
                        SELECT indicador, SUM(meta) as total_meta
                        FROM metas
                        WHERE anio = ? AND mes = ?
                        GROUP BY indicador
                    ", [$anioObjetivo, $mesObjetivo]);

                    foreach ($metasManuales as $mm) {
                        $ind = strtolower($mm->indicador);
                        $valMeta = (float) $mm->total_meta;
                        
                        if ($valMeta > 0) {
                            if (str_contains($ind, 'venta')) { $objVentas = $valMeta; $isManualVentas = true; }
                            elseif (str_contains($ind, 'empeño') || str_contains($ind, 'empeno')) { $objEmpenos = $valMeta; $isManualEmpenos = true; }
                            elseif (str_contains($ind, 'interes')) { $objIntereses = $valMeta; $isManualIntereses = true; }
                            elseif (str_contains($ind, 'utilidad')) { $objUtilidadOperativa = $valMeta; $isManualUtilidad = true; }
                        }
                    }
                } catch (\Exception $e) {
                    // Si la tabla no existe en esta db particular, se ignora y se usan las automáticas
                }

                $isManualBranch = ($isManualVentas || $isManualEmpenos || $isManualIntereses || $isManualUtilidad);

                $branchKPIs[$sucursal->nombre] = [
                    'id' => $sucursal->id_valora_mas,
                    'is_manual' => $isManualBranch,
                    'real_ventas' => $branchData['real_ventas'],
                    'meta_ventas' => $objVentas,
                    'pct_ventas' => $objVentas > 0 ? ($branchData['real_ventas'] / $objVentas) * 100 : 0,

                    'real_empenos' => $branchData['real_empenos'],
                    'meta_empenos' => $objEmpenos,
                    'pct_empenos' => $objEmpenos > 0 ? ($branchData['real_empenos'] / $objEmpenos) * 100 : 0,

                    'real_intereses' => $branchData['real_intereses'],
                    'meta_intereses' => $objIntereses,
                    'pct_intereses' => $objIntereses > 0 ? ($branchData['real_intereses'] / $objIntereses) * 100 : 0,

                    'real_utilidad' => $branchData['real_utilidad_operativa'],
                    'meta_utilidad' => $objUtilidadOperativa,
                    'pct_utilidad' => $objUtilidadOperativa > 0 ? ($branchData['real_utilidad_operativa'] / $objUtilidadOperativa) * 100 : 0,
                    
                    'semaforo' => $this->getSemaforo( ($objVentas > 0 ? ($branchData['real_ventas'] / $objVentas) * 100 : 0) )
                ];

                // Agregar los vectores históricos a la super matriz global
                foreach ($branchData['history'] as $hk => $hv) {
                    if (!isset($global_history[$hk])) {
                        $global_history[$hk] = ['ventas' => 0, 'utilidad_vta' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0];
                    }
                    $global_history[$hk]['ventas'] += $hv['ventas'];
                    $global_history[$hk]['utilidad_vta'] += $hv['utilidad_vta'];
                    $global_history[$hk]['empenos'] += $hv['empenos'];
                    $global_history[$hk]['intereses'] += $hv['intereses'];
                    $global_history[$hk]['gastos'] += $hv['gastos'];
                }

                $real_ventasTotales += $branchData['real_ventas'];
                $real_empenosTotales += $branchData['real_empenos'];
                $real_interesesTotales += $branchData['real_intereses'];
                $real_utilidadOperativa += $branchData['real_utilidad_operativa'];

                $metaG_ventasTotales += $objVentas;
                $metaG_empenosTotales += $objEmpenos;
                $metaG_interesesTotales += $objIntereses;
                $metaG_utilidadOperativa += $objUtilidadOperativa;

            } catch (\Exception $e) {
                Log::error("Error en Metas/Forecast sucursal {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // ============================================
        // CÁLCULO DE METAS GLOBALES
        // ============================================
        $kpiVentas = [];
        $kpiEmpenos = [];
        $kpiIntereses = [];
        $kpiUtilidad = [];

        // Generar historia de meses ordenadas para la gráfica
        ksort($global_history);
        $globalLabels = [];
        $globalTendenciaVentas = [];
        $globalRealesVentas = [];
        $globalVentasLy = []; // Año Anterior

        foreach ($global_history as $k => $v) {
            $globalLabels[] = $k;
            $globalRealesVentas[] = $v['ventas'];
            
            // Buscar año anterior si existe
            $partes = explode('-', $k);
            $ly_k = sprintf("%04d-%02d", ((int)$partes[0] - 1), (int)$partes[1]);
            $globalVentasLy[] = $global_history[$ly_k]['ventas'] ?? 0;
            
            // La meta histórica de ese mes
            $metaAnt = $this->calcularMeta($global_history, 'ventas', (int)$partes[1], $mesesHistorico, 1, (int)$partes[0], (int)$partes[1], true);
            $globalTendenciaVentas[] = $metaAnt;
        }

        $globalMetaVentas = $metaG_ventasTotales;
        $globalMetaEmpenos = $metaG_empenosTotales;
        $globalMetaIntereses = $metaG_interesesTotales;
        $globalMetaUtilidad = $metaG_utilidadOperativa;

        // Si meta 0, pct = 0
        $pctGVentas = $globalMetaVentas > 0 ? ($real_ventasTotales / $globalMetaVentas) * 100 : 0;
        $pctGEmpenos = $globalMetaEmpenos > 0 ? ($real_empenosTotales / $globalMetaEmpenos) * 100 : 0;
        $pctGIntereses = $globalMetaIntereses > 0 ? ($real_interesesTotales / $globalMetaIntereses) * 100 : 0;
        $pctGUtilidad = $globalMetaUtilidad > 0 ? ($real_utilidadOperativa / $globalMetaUtilidad) * 100 : 0;

        return response()->json([
            'globals' => [
                'ventas' => ['real' => $real_ventasTotales, 'meta' => $globalMetaVentas, 'pct' => $pctGVentas, 'diff' => $real_ventasTotales - $globalMetaVentas],
                'empenos' => ['real' => $real_empenosTotales, 'meta' => $globalMetaEmpenos, 'pct' => $pctGEmpenos, 'diff' => $real_empenosTotales - $globalMetaEmpenos],
                'intereses' => ['real' => $real_interesesTotales, 'meta' => $globalMetaIntereses, 'pct' => $pctGIntereses, 'diff' => $real_interesesTotales - $globalMetaIntereses],
                'utilidad' => ['real' => $real_utilidadOperativa, 'meta' => $globalMetaUtilidad, 'pct' => $pctGUtilidad, 'diff' => $real_utilidadOperativa - $globalMetaUtilidad],
            ],
            'chartTimeline' => [
                'labels' => $globalLabels,
                'real' => $globalRealesVentas,
                'ly' => $globalVentasLy,
                'tendencia' => $globalTendenciaVentas
            ],
            'branchKPIs' => array_values($branchKPIs)
        ]);
    }

    /**
     * Calcula la proyección y meta con estacionalidad
     */
    private function calcularMeta($historyData, $metric, $mesObjetivo, $numMesesHist, $factorCrecimiento, $anioObj, $mesObj, $esRetroactivo = false)
    {
        $x = [];
        $y = [];
        $valoresEstacionales = []; // Guardar valores de este mismo mes objetivo en años pasados
        $sumaTotal = 0;
        $countMeses = 0;

        // Filtrar historia estrictamente menor a la fecha objetivo para no contaminar el futuro
        $topeClave = sprintf("%04d-%02d", $anioObj, $mesObj);

        $index = 1;
        foreach ($historyData as $k => $data) {
            if ($k >= $topeClave) continue; // Ignoramos el mes curso para calcular su forecast

            $val = $data[$metric];
            $x[] = $index;
            $y[] = $val;
            $sumaTotal += $val;
            $countMeses++;

            $partes = explode('-', $k);
            if ((int)$partes[1] == $mesObjetivo) {
                $valoresEstacionales[] = $val;
            }

            $index++;
        }

        if ($countMeses == 0) return 0;

        // Regresión Lineal
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        
        $sumXY = 0;
        $sumX2 = 0;
        for ($i=0; $i<$n; $i++) {
            $sumXY += ($x[$i] * $y[$i]);
            $sumX2 += ($x[$i] * $x[$i]);
        }

        $denominator = ($n * $sumX2) - ($sumX * $sumX);
        if ($denominator == 0) {
            $m = 0;
            $b = $sumY / $n;
        } else {
            $m = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
            $b = ($sumY - ($m * $sumX)) / $n;
        }

        // Proyectar para el X siguiente
        $nextX = $n + 1;
        $tendenciaLineal = ($m * $nextX) + $b;

        // Índice estacionalidad
        // Promedio del mes especifico / Promedio Histórico General
        $promedioGeneral = $sumaTotal / $n;
        $promedioMesEspecifico = count($valoresEstacionales) > 0 ? array_sum($valoresEstacionales) / count($valoresEstacionales) : $promedioGeneral;

        $indiceEstacionalidad = $promedioGeneral > 0 ? ($promedioMesEspecifico / $promedioGeneral) : 1;

        $meta = $tendenciaLineal * $factorCrecimiento * $indiceEstacionalidad;

        // Si da negativo (fallo de regresión abrupta) aplanamos a 0
        return $meta > 0 ? $meta : (($sumY / $n) * $factorCrecimiento * $indiceEstacionalidad); 
    }

    private function calcularMetaUtilidad($historyData, $mesObjetivo, $numMesesHist, $factorCrecimiento, $anioObj, $mesObj)
    {
        // La utilidad es ventas + intereses - gastos
        // Construimos una pseudo-métrica temporal en la historia para correr la regresión
        $tempHistory = [];
        foreach ($historyData as $k => $d) {
            $tempHistory[$k] = [
                'calc_utilidad' => ($d['utilidad_vta'] + $d['intereses']) - $d['gastos']
            ];
        }

        return $this->calcularMeta($tempHistory, 'calc_utilidad', $mesObjetivo, $numMesesHist, $factorCrecimiento, $anioObj, $mesObj);
    }

    private function getSemaforo($pct)
    {
        if ($pct >= 90) return 'verde';
        if ($pct >= 70) return 'amarillo';
        return 'rojo';
    }
}
