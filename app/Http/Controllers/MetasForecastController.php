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
    public function index()
    {
        $mesesHistorico = 12;
        $crecimiento = 5; // 5% por defeto
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        $fechaInicio = Carbon::now()->startOfMonth()->toDateString();
        $fechaFin = Carbon::now()->toDateString();

        return view('metas-forecast.index', compact('mesesHistorico', 'crecimiento', 'sucursales', 'fechaInicio', 'fechaFin'));
    }

    public function data(Request $request)
    {
        $mesesHistorico = (int) $request->input('meses_historico', 12);
        $crecimientoPorcentaje = (float) $request->input('crecimiento', 5);
        $sucursalId = $request->input('sucursal_id');

        $fechaInicio = $request->input('fecha_inicio', Carbon::now()->startOfMonth()->toDateString());
        $fechaFin = $request->input('fecha_fin', Carbon::now()->toDateString());

        $crecimientoFactor = 1 + ($crecimientoPorcentaje / 100);
        
        $carbonFin = Carbon::parse($fechaFin);
        $mesObjetivo = $carbonFin->month;
        $anioObjetivo = $carbonFin->year;

        $fechaInicioHistorico = Carbon::parse($fechaInicio)->subMonths($mesesHistorico)->startOfMonth()->toDateString();
        $fechaTopeActual = Carbon::parse($fechaFin)->endOfDay()->toDateString(); 

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        if ($sucursalId) {
            $sucursales = $sucursales->where('id_valora_mas', $sucursalId);
        }

        $baseConfig = Config::get('database.connections.mysql');

        // Variables Globales Reales del Periodo
        $real_ventasTotales = 0;
        $real_empenosTotales = 0;
        $real_interesesTotales = 0;
        $real_utilidadOperativa = 0;

        // Metas Globales (Suma de Sucursales que ya incluyen override manual)
        $metaG_ventasTotales = 0;
        $metaG_empenosTotales = 0;
        $metaG_interesesTotales = 0;
        $metaG_utilidadOperativa = 0;

        // Metas Automáticas Globales
        $autoG_ventas = 0;
        $autoG_empenos = 0;
        $autoG_intereses = 0;
        $autoG_utilidad = 0;

        // Metas Manuales Globales
        $manualG_ventas = 0;
        $manualG_empenos = 0;
        $manualG_intereses = 0;
        $manualG_utilidad = 0;

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
                        END) as prestamo_base,
                        SUM(CASE 
                            WHEN CAST(ve.f_venta AS DATE) BETWEEN ? AND ? THEN dv.venta10 
                            ELSE 0 
                        END) as real_periodo_venta,
                        SUM(CASE 
                            WHEN CAST(ve.f_venta AS DATE) BETWEEN ? AND ? THEN (dv.venta10 - (CASE 
                                WHEN ve.cod_tipo_prenda = 1 THEN COALESCE(al.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 2 THEN COALESCE(au.prestamo, 0)
                                WHEN ve.cod_tipo_prenda = 3 THEN COALESCE(va.prestamo, 0)
                                ELSE 0
                            END))
                            ELSE 0 
                        END) as real_periodo_utilidad_vta
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    LEFT JOIN alhajas al ON ve.cod_tipo_prenda = 1 AND al.cod_alhaja = dv.cod_prenda
                    LEFT JOIN autos au ON ve.cod_tipo_prenda = 2 AND au.cod_auto = dv.cod_prenda
                    LEFT JOIN varios va ON ve.cod_tipo_prenda = 3 AND va.cod_varios = dv.cod_prenda
                    WHERE ve.f_cancela IS NULL
                    AND CAST(ve.f_venta AS DATE) BETWEEN ? AND ?
                    GROUP BY YEAR(ve.f_venta), MONTH(ve.f_venta)
                ", [$fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicioHistorico, $fechaTopeActual]);

                $realUtilidadVentaPeriodo = 0;
                foreach ($ventasHist as $vh) {
                    $key = sprintf("%04d-%02d", $vh->anio, $vh->mes);
                    if (!isset($branchData['history'][$key])) {
                        $branchData['history'][$key] = ['ventas' => 0, 'utilidad_vta' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0];
                    }
                    $branchData['history'][$key]['ventas'] += (float)$vh->total_venta;
                    $branchData['history'][$key]['utilidad_vta'] += ((float)$vh->total_venta - (float)$vh->prestamo_base);

                    $branchData['real_ventas'] += (float)$vh->real_periodo_venta;
                    $realUtilidadVentaPeriodo += (float)$vh->real_periodo_utilidad_vta;
                }

                // 2. EXTRAER EMPEÑOS E INTERESES HISTORICOS (Movimientos)
                $movsHist = DB::connection($connectionName)->select("
                    SELECT 
                        YEAR(mo.f_alta) as anio,
                        MONTH(mo.f_alta) as mes,
                        SUM(CASE WHEN mo.cod_tipo_movimiento = 1 THEN con.prestamo ELSE 0 END) as empenos,
                        SUM(CASE 
                            WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - COALESCE(con.prestamo, 0))
                            WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - COALESCE(ca.abono, 0))
                            ELSE 0
                        END) as intereses,
                        SUM(CASE 
                            WHEN CAST(mo.f_alta AS DATE) BETWEEN ? AND ? AND mo.cod_tipo_movimiento = 1 THEN con.prestamo 
                            ELSE 0 
                        END) as real_periodo_empenos,
                        SUM(CASE 
                            WHEN CAST(mo.f_alta AS DATE) BETWEEN ? AND ? THEN (
                                CASE 
                                    WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - COALESCE(con.prestamo, 0))
                                    WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - COALESCE(ca.abono, 0))
                                    ELSE 0
                                END
                            )
                            ELSE 0 
                        END) as real_periodo_intereses
                    FROM movimientos mo
                    LEFT JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                    WHERE mo.f_cancela IS NULL AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)
                    AND CAST(mo.f_alta AS DATE) BETWEEN ? AND ?
                    GROUP BY YEAR(mo.f_alta), MONTH(mo.f_alta)
                ", [$fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicioHistorico, $fechaTopeActual]);

                foreach ($movsHist as $mh) {
                    $key = sprintf("%04d-%02d", $mh->anio, $mh->mes);
                    if (!isset($branchData['history'][$key])) {
                        $branchData['history'][$key] = ['ventas' => 0, 'utilidad_vta' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0];
                    }
                    $branchData['history'][$key]['empenos'] += (float)$mh->empenos;
                    $branchData['history'][$key]['intereses'] += (float)$mh->intereses;

                    $branchData['real_empenos'] += (float)$mh->real_periodo_empenos;
                    $branchData['real_intereses'] += (float)$mh->real_periodo_intereses;
                }

                // 3. EXTRAER GASTOS (Para Utilidad Operativa)
                $gastosHist = DB::connection($connectionName)->select("
                    SELECT 
                        YEAR(gas.f_solicitado) as anio,
                        MONTH(gas.f_solicitado) as mes,
                        SUM(gas.solicitado) as total_gasto,
                        SUM(CASE 
                            WHEN CAST(gas.f_solicitado AS DATE) BETWEEN ? AND ? THEN gas.solicitado 
                            ELSE 0 
                        END) as real_periodo_gasto
                    FROM gastos gas
                    WHERE gas.activo = 1 AND gas.cod_estatus = 2
                    AND CAST(gas.f_solicitado AS DATE) BETWEEN ? AND ?
                    GROUP BY YEAR(gas.f_solicitado), MONTH(gas.f_solicitado)
                ", [$fechaInicio, $fechaFin, $fechaInicioHistorico, $fechaTopeActual]);

                $gastoPeriodo = 0;
                foreach ($gastosHist as $gh) {
                    $key = sprintf("%04d-%02d", $gh->anio, $gh->mes);
                    if (!isset($branchData['history'][$key])) {
                        $branchData['history'][$key] = ['ventas' => 0, 'utilidad_vta' => 0, 'empenos' => 0, 'intereses' => 0, 'gastos' => 0];
                    }
                    $branchData['history'][$key]['gastos'] += (float)$gh->total_gasto;

                    $gastoPeriodo += (float)$gh->real_periodo_gasto;
                }

            // Consolidar Real del Periodo Utilidad Operativa
                $branchData['real_utilidad_operativa'] = ($realUtilidadVentaPeriodo + $branchData['real_intereses']) - $gastoPeriodo;
                // ============================================
                // CÁLCULO DE METAS ESTADÍSTICAS AUTOMÁTICAS
                // ============================================
                $objVentas = $this->calcularMeta($branchData['history'], 'ventas', $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);
                $objEmpenos = $this->calcularMeta($branchData['history'], 'empenos', $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);
                $objIntereses = $this->calcularMeta($branchData['history'], 'intereses', $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);
                $objUtilidadOperativa = $this->calcularMetaUtilidad($branchData['history'], $mesObjetivo, $mesesHistorico, $crecimientoFactor, $anioObjetivo, $mesObjetivo);

                $autoV = $objVentas;
                $autoE = $objEmpenos;
                $autoI = $objIntereses;
                $autoU = $objUtilidadOperativa;

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

                $autoG_ventas += $autoV;
                $autoG_empenos += $autoE;
                $autoG_intereses += $autoI;
                $autoG_utilidad += $autoU;

                $manualG_ventas += $isManualVentas ? $objVentas : 0;
                $manualG_empenos += $isManualEmpenos ? $objEmpenos : 0;
                $manualG_intereses += $isManualIntereses ? $objIntereses : 0;
                $manualG_utilidad += $isManualUtilidad ? $objUtilidadOperativa : 0;

                $isManualBranch = ($isManualVentas || $isManualEmpenos || $isManualIntereses || $isManualUtilidad);

                $pctVentas = $objVentas > 0 ? ($branchData['real_ventas'] / $objVentas) * 100 : 0;
                $pctEmpenos = $objEmpenos > 0 ? ($branchData['real_empenos'] / $objEmpenos) * 100 : 0;
                $pctIntereses = $objIntereses > 0 ? ($branchData['real_intereses'] / $objIntereses) * 100 : 0;
                $pctUtilidad = $objUtilidadOperativa > 0 ? ($branchData['real_utilidad_operativa'] / $objUtilidadOperativa) * 100 : 0;

                $branchKPIs[$sucursal->nombre] = [
                    'id' => $sucursal->nombre,
                    'id_valora_mas' => $sucursal->id_valora_mas,
                    'is_manual' => $isManualBranch,
                    
                    'real_ventas' => $branchData['real_ventas'],
                    'meta_ventas' => $objVentas,
                    'pct_ventas' => $pctVentas,
                    'semaforo_ventas' => $this->getSemaforo($pctVentas),

                    'real_empenos' => $branchData['real_empenos'],
                    'meta_empenos' => $objEmpenos,
                    'pct_empenos' => $pctEmpenos,
                    'semaforo_empenos' => $this->getSemaforo($pctEmpenos),

                    'real_intereses' => $branchData['real_intereses'],
                    'meta_intereses' => $objIntereses,
                    'pct_intereses' => $pctIntereses,
                    'semaforo_intereses' => $this->getSemaforo($pctIntereses),

                    'real_utilidad' => $branchData['real_utilidad_operativa'],
                    'meta_utilidad' => $objUtilidadOperativa,
                    'pct_utilidad' => $pctUtilidad,
                    'semaforo_utilidad' => $this->getSemaforo($pctUtilidad),
                    
                    'semaforo' => $this->getSemaforo($pctVentas)
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

        // =========================================================================
        // SINCRONIZACIÓN OFICIAL CON TABLERO DE CONTROL (Valores Reales y Metas)
        // =========================================================================
        try {
            $tableroCtrl = new TableroControlController();
            $tableroReq = new Request([
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'sucursal_id' => $sucursalId,
                'sistema' => 'varamas'
            ]);
            $tResp = json_decode($tableroCtrl->data($tableroReq)->getContent(), true);
            $tablero = $tResp['tablero'] ?? $tResp;

            $tVentas = $this->getIndicatorData($tablero, 'Ventas');
            $tEmpenos = $this->getIndicatorData($tablero, 'Empeño');
            $tIntereses = $this->getIndicatorData($tablero, 'Intereses');
            $tUtilidad = $this->getIndicatorData($tablero, 'Utilidad Neta del Mes');

            // Asignar los valores reales exactamente calculados por Tablero de Control
            $real_ventasTotales = $tVentas['real'];
            $real_empenosTotales = $tEmpenos['real'];
            $real_interesesTotales = $tIntereses['real'];
            $real_utilidadOperativa = $tUtilidad['real'];

            // Si el Tablero de Control tiene metas corporativas registradas (> 0), se toman como metas oficiales
            if ($tVentas['meta'] > 0) {
                $manualG_ventas = $tVentas['meta'];
                $metaG_ventasTotales = $tVentas['meta'];
            }
            if ($tEmpenos['meta'] > 0) {
                $manualG_empenos = $tEmpenos['meta'];
                $metaG_empenosTotales = $tEmpenos['meta'];
            }
            if ($tIntereses['meta'] > 0) {
                $manualG_intereses = $tIntereses['meta'];
                $metaG_interesesTotales = $tIntereses['meta'];
            }
            if ($tUtilidad['meta'] > 0) {
                $manualG_utilidad = $tUtilidad['meta'];
                $metaG_utilidadOperativa = $tUtilidad['meta'];
            }

            if ($sucursalId && !empty($branchKPIs)) {
                $branchKey = array_key_first($branchKPIs);
                if ($branchKey) {
                    $branchKPIs[$branchKey]['real_ventas'] = $real_ventasTotales;
                    $branchKPIs[$branchKey]['meta_ventas'] = $metaG_ventasTotales;
                    $branchKPIs[$branchKey]['pct_ventas'] = $metaG_ventasTotales > 0 ? ($real_ventasTotales / $metaG_ventasTotales) * 100 : 0;
                    $branchKPIs[$branchKey]['real_empenos'] = $real_empenosTotales;
                    $branchKPIs[$branchKey]['meta_empenos'] = $metaG_empenosTotales;
                    $branchKPIs[$branchKey]['pct_empenos'] = $metaG_empenosTotales > 0 ? ($real_empenosTotales / $metaG_empenosTotales) * 100 : 0;
                    $branchKPIs[$branchKey]['real_intereses'] = $real_interesesTotales;
                    $branchKPIs[$branchKey]['meta_intereses'] = $metaG_interesesTotales;
                    $branchKPIs[$branchKey]['pct_intereses'] = $metaG_interesesTotales > 0 ? ($real_interesesTotales / $metaG_interesesTotales) * 100 : 0;
                    $branchKPIs[$branchKey]['real_utilidad'] = $real_utilidadOperativa;
                    $branchKPIs[$branchKey]['meta_utilidad'] = $metaG_utilidadOperativa;
                    $branchKPIs[$branchKey]['pct_utilidad'] = $metaG_utilidadOperativa > 0 ? ($real_utilidadOperativa / $metaG_utilidadOperativa) * 100 : 0;

                    $branchKPIs[$branchKey]['semaforo_ventas'] = $this->getSemaforo($branchKPIs[$branchKey]['pct_ventas']);
                    $branchKPIs[$branchKey]['semaforo_empenos'] = $this->getSemaforo($branchKPIs[$branchKey]['pct_empenos']);
                    $branchKPIs[$branchKey]['semaforo_intereses'] = $this->getSemaforo($branchKPIs[$branchKey]['pct_intereses']);
                    $branchKPIs[$branchKey]['semaforo_utilidad'] = $this->getSemaforo($branchKPIs[$branchKey]['pct_utilidad']);
                }
            }
        } catch (\Exception $e) {
            Log::error("Error sincronizando con TableroControlController: " . $e->getMessage());
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

        // ============================================
        // CÁLCULO DE ÍNDICES DE ESTACIONALIDAD MENSUALES
        // ============================================
        $estacionalidad = [
            'ventas' => array_fill(1, 12, 1.0),
            'empenos' => array_fill(1, 12, 1.0),
            'intereses' => array_fill(1, 12, 1.0),
            'utilidad' => array_fill(1, 12, 1.0)
        ];

        $totalH_ventas = 0; $totalH_empenos = 0; $totalH_intereses = 0; $totalH_utilidad = 0;
        $countH_meses = 0;

        $mesesPorcentaje = [];
        for ($m = 1; $m <= 12; $m++) {
            $mesesPorcentaje[$m] = ['ventas' => [], 'empenos' => [], 'intereses' => [], 'utilidad' => []];
        }

        foreach ($global_history as $k => $h) {
            $partes = explode('-', $k);
            $mesAnio = (int)$partes[1];
            
            $vVal = (float)$h['ventas'];
            $eVal = (float)$h['empenos'];
            $iVal = (float)$h['intereses'];
            $uVal = ((float)($h['utilidad_vta'] ?? 0) + $iVal) - (float)$h['gastos'];

            $totalH_ventas += $vVal;
            $totalH_empenos += $eVal;
            $totalH_intereses += $iVal;
            $totalH_utilidad += $uVal;
            $countH_meses++;

            $mesesPorcentaje[$mesAnio]['ventas'][] = $vVal;
            $mesesPorcentaje[$mesAnio]['empenos'][] = $eVal;
            $mesesPorcentaje[$mesAnio]['intereses'][] = $iVal;
            $mesesPorcentaje[$mesAnio]['utilidad'][] = $uVal;
        }

        if ($countH_meses > 0) {
            $promG_ventas = $totalH_ventas / $countH_meses;
            $promG_empenos = $totalH_empenos / $countH_meses;
            $promG_intereses = $totalH_intereses / $countH_meses;
            $promG_utilidad = $totalH_utilidad / $countH_meses;

            for ($m = 1; $m <= 12; $m++) {
                $avgV = count($mesesPorcentaje[$m]['ventas']) > 0 ? array_sum($mesesPorcentaje[$m]['ventas']) / count($mesesPorcentaje[$m]['ventas']) : $promG_ventas;
                $avgE = count($mesesPorcentaje[$m]['empenos']) > 0 ? array_sum($mesesPorcentaje[$m]['empenos']) / count($mesesPorcentaje[$m]['empenos']) : $promG_empenos;
                $avgI = count($mesesPorcentaje[$m]['intereses']) > 0 ? array_sum($mesesPorcentaje[$m]['intereses']) / count($mesesPorcentaje[$m]['intereses']) : $promG_intereses;
                $avgU = count($mesesPorcentaje[$m]['utilidad']) > 0 ? array_sum($mesesPorcentaje[$m]['utilidad']) / count($mesesPorcentaje[$m]['utilidad']) : $promG_utilidad;

                $estacionalidad['ventas'][$m] = $promG_ventas > 0 ? $avgV / $promG_ventas : 1.0;
                $estacionalidad['empenos'][$m] = $promG_empenos > 0 ? $avgE / $promG_empenos : 1.0;
                $estacionalidad['intereses'][$m] = $promG_intereses > 0 ? $avgI / $promG_intereses : 1.0;
                $estacionalidad['utilidad'][$m] = $promG_utilidad > 0 ? $avgU / $promG_utilidad : 1.0;
            }
        }

        $estacionalidadPlanos = [
            'ventas' => array_values($estacionalidad['ventas']),
            'empenos' => array_values($estacionalidad['empenos']),
            'intereses' => array_values($estacionalidad['intereses']),
            'utilidad' => array_values($estacionalidad['utilidad']),
        ];

        // ============================================
        // COMPARATIVA DE METAS (AUTOMÁTICA VS MANUAL)
        // ============================================
        $comparativaMetas = [
            [
                'indicador' => 'Ventas',
                'meta_automatica' => $autoG_ventas,
                'meta_manual' => $manualG_ventas,
                'meta_aplicada' => $globalMetaVentas,
                'tipo_meta' => $manualG_ventas > 0 ? 'Manual' : 'Automática'
            ],
            [
                'indicador' => 'Empeños',
                'meta_automatica' => $autoG_empenos,
                'meta_manual' => $manualG_empenos,
                'meta_aplicada' => $globalMetaEmpenos,
                'tipo_meta' => $manualG_empenos > 0 ? 'Manual' : 'Automática'
            ],
            [
                'indicador' => 'Intereses',
                'meta_automatica' => $autoG_intereses,
                'meta_manual' => $manualG_intereses,
                'meta_aplicada' => $globalMetaIntereses,
                'tipo_meta' => $manualG_intereses > 0 ? 'Manual' : 'Automática'
            ],
            [
                'indicador' => 'Utilidad Operativa',
                'meta_automatica' => $autoG_utilidad,
                'meta_manual' => $manualG_utilidad,
                'meta_aplicada' => $globalMetaUtilidad,
                'tipo_meta' => $manualG_utilidad > 0 ? 'Manual' : 'Automática'
            ]
        ];

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
            'estacionalidad' => $estacionalidadPlanos,
            'comparativaMetas' => $comparativaMetas,
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

    /**
     * Extrae de forma segura el valor real y meta de un indicador del Tablero de Control
     */
    private function getIndicatorData($t, $ind)
    {
        if (!isset($t[$ind])) return ['real' => 0.0, 'meta' => 0.0];
        $real = (float)($t[$ind]['_total']['avance'] ?? 0);
        $meta = (float)($t[$ind]['_total']['meta'] ?? 0);
        if ($real == 0 && $meta == 0) {
            foreach (['MERCANCIA GENERAL', 'ORO', 'PLATA', 'AUTOS'] as $cat) {
                $real += (float)($t[$ind][$cat]['avance'] ?? 0);
                $meta += (float)($t[$ind][$cat]['meta'] ?? 0);
            }
        }
        return ['real' => $real, 'meta' => $meta];
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
