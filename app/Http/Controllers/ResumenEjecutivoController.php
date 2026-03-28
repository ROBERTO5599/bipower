<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class ResumenEjecutivoController extends Controller
{
    // Carga la vista base sin datos (renderizado rápido)
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('resumen-ejecutivo.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    // Endpoint AJAX para obtener los datos agregados
    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        $fechaFinQuery = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        $sucursalId = $request->input('sucursal_id');

        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');

        // Variables Globales
        $totalIngresos = 0;
        $totalIngresosVentasIntereses = 0; // Ventas + Intereses
        $totalEgresos = 0;
        $totalGastosOperativos = 0;

        // Inventario
        $inventarioPisoVentaTotal = 0;
        $inventarioPisoPrestamoTotal = 0;
        $inventarioVarios = 0;
        $inventarioOro = 0;
        $invOro = 0;
        $invPlata = 0;
        $invVarios = 0;
        $invAutos = 0;

        // Cartera de empeño
        $carteraVigente = 0;
        $carteraVencida = 0;

        // Ventas por categoría
        $ventasVarios = 0;
        $ventasOro = 0;
        $ventasRemate = 0;
        $ventasPlata = 0;
        $ventasAutos = 0;

        $empenosData = ['contratos' => 0, 'prestamo' => 0];
        
        // Variables para transacciones
        $totalTransaccionesVentas = 0;
        $totalContratosApartados = 0;
        
        // Variables para cada tipo de ingreso
        $utilidadVentaGlobal = 0;
        $interesesGlobal = 0;
        $desempenosGlobal = 0;
        $ventasGlobal = 0;
        $apartadosLiquidadosGlobal = 0;
        $abonoApartadoGlobal = 0;
        $abonoCapitalGlobal = 0;
        $engancheCreditoGlobal = 0;
        $abonoCreditoGlobal = 0;
        $certificadoConfianzaGlobal = 0;
        
        // Para calcular utilidad neta
        $ventasTotalesGlobal = 0;
        $prestamoVentasGlobal = 0;
        
        // Variables para costos y gastos
        $costoVentasGlobal = 0;

        // Balance General y Flujo de Efectivo
        $balanceGeneral = [
            'activo_total' => 0,
            'pasivo_total' => 0,
            'capital_total' => 0,
            'efectivo_inicial' => 0,
            'efectivo_final' => 0,
            'flujo_neto' => 0
        ];

        // Metas por sucursal (ejemplo - estas deberían venir de una tabla de metas)
        $metasSucursales = $this->getMetasSucursales();

        $branchKPIs = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi';

            $b_ingresos = 0;
            $b_ingresosVentasIntereses = 0;
            $b_egresos = 0;
            $b_utilidad = 0;
            $b_invTotal = 0;
            
            // Variables por sucursal
            $b_utilidadVenta = 0;
            $b_intereses = 0;
            $b_desempenos = 0;
            $b_ventas = 0;
            $b_prestamoVentas = 0;
            $b_apartadosLiquidados = 0;
            $b_abonoApartado = 0;
            $b_abonoCapital = 0;
            $b_engancheCredito = 0;
            $b_abonoCredito = 0;
            $b_certificadoConfianza = 0;
            
            // Variables por sucursal
            $b_transaccionesVentas = 0;
            $b_contratosApartados = 0;
            $b_costoVentas = 0;

            // Cartera por sucursal
            $b_carteraVigente = 0;
            $b_carteraVencida = 0;

            // Ventas por categoría
            $b_ventasVarios = 0;
            $b_ventasOro = 0;
            $b_ventasRemate = 0;
            $b_ventasPlata = 0;
            $b_ventasAutos = 0;

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                // 1. GASTOS (Egresos)
                $gastosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(gas.solicitado), 0) AS TotalGastos
                    FROM gastos gas
                    INNER JOIN movimientos mov ON gas.cod_movimiento = mov.cod_movimiento
                    WHERE gas.activo = 1
                      AND gas.cod_estatus = 2
                      AND gas.f_solicitado >= :fechaDel
                      AND gas.f_solicitado <= :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_egresos = (float)($gastosResult->TotalGastos ?? 0);

                // 2. VENTAS por categoría
                $ventasCategoriaResult = DB::connection($connectionName)->select("
                    SELECT
                        ve.cod_tipo_prenda,
                        SUM(dv.venta10) as total_venta,
                        COUNT(DISTINCT ve.cod_venta) as transacciones
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    WHERE ve.f_cancela IS NULL 
                      AND CAST(ve.f_venta AS DATE) BETWEEN :fechaDel AND :fechaAl
                    GROUP BY ve.cod_tipo_prenda
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                foreach ($ventasCategoriaResult as $venta) {
                    $monto = (float)$venta->total_venta;
                    switch ($venta->cod_tipo_prenda) {
                        case 1: // Alhajas
                            $b_ventasOro += $monto;
                            $b_ventasPlata += $monto * 0.3; // Estimación: 30% son de plata
                            $b_ventasVarios += $monto * 0.2; // Estimación: 20% son varios
                            break;
                        case 2: // Autos
                            $b_ventasAutos += $monto;
                            break;
                        case 3: // Varios
                            $b_ventasVarios += $monto;
                            break;
                    }
                }

                // Ventas totales y préstamo
                $ventasResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        COALESCE(SUM(dv.venta10), 0) AS total_ventas,
                        COALESCE(SUM(
                            CASE
                                WHEN ve.cod_tipo_prenda = 1 THEN COALESCE((SELECT prestamo FROM alhajas WHERE cod_alhaja = dv.cod_prenda), 0)
                                WHEN ve.cod_tipo_prenda = 2 THEN COALESCE((SELECT prestamo FROM autos WHERE cod_auto = dv.cod_prenda), 0)
                                WHEN ve.cod_tipo_prenda = 3 THEN COALESCE((SELECT prestamo FROM varios WHERE cod_varios = dv.cod_prenda), 0)
                                ELSE 0
                            END
                        ), 0) as prestamo_ventas,
                        COUNT(DISTINCT ve.cod_venta) AS total_transacciones
                    FROM movimientos mo
                    INNER JOIN ventas ve ON ve.cod_movimiento = mo.cod_movimiento
                    INNER JOIN detalle_venta dv ON dv.cod_venta = ve.cod_venta
                    WHERE mo.f_cancela IS NULL AND mo.cod_tipo_movimiento IN (5, 6) AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_ventas = (float)($ventasResult->total_ventas ?? 0);
                $b_prestamoVentas_directas = (float)($ventasResult->prestamo_ventas ?? 0);
                $b_transaccionesVentas = (int)($ventasResult->total_transacciones ?? 0);
                
                // 3. APARTADOS LIQUIDADOS (movimiento 12)
                $apartadosLiquidadosResult = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 THEN COALESCE((SELECT precio FROM alhajas WHERE cod_alhaja = da.cod_prenda), 0)
                                WHEN ap.cod_tipo_prenda = 2 THEN COALESCE((SELECT precio FROM autos WHERE cod_auto = da.cod_prenda), 0)
                                WHEN ap.cod_tipo_prenda = 3 THEN COALESCE((SELECT precio FROM varios WHERE cod_varios = da.cod_prenda), 0)
                                ELSE 0
                            END
                        ), 0) AS total_apartados_precio,
                        COALESCE(SUM(
                            CASE
                                WHEN ap.cod_tipo_prenda = 1 THEN COALESCE((SELECT prestamo FROM alhajas WHERE cod_alhaja = da.cod_prenda), 0)
                                WHEN ap.cod_tipo_prenda = 2 THEN COALESCE((SELECT prestamo FROM autos WHERE cod_auto = da.cod_prenda), 0)
                                WHEN ap.cod_tipo_prenda = 3 THEN COALESCE((SELECT prestamo FROM varios WHERE cod_varios = da.cod_prenda), 0)
                                ELSE 0
                            END
                        ), 0) AS total_apartados_prestamo,
                        COUNT(DISTINCT ap.cod_apartado) AS total_contratos
                    FROM apartado_pagos apg
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                    INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                    WHERE apg.f_cancela IS NULL 
                      AND mo.cod_tipo_movimiento = 12 
                      AND CAST(apg.f_pago AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                
                $b_apartadosLiquidados = (float)($apartadosLiquidadosResult->total_apartados_precio ?? 0);
                $b_apartadosLiquidados_prestamo = (float)($apartadosLiquidadosResult->total_apartados_prestamo ?? 0);
                $b_contratosApartados = (int)($apartadosLiquidadosResult->total_contratos ?? 0);

                // Add up total sales and costs
                $b_ventasTotales = $b_ventas + $b_apartadosLiquidados;
                $b_prestamoVentas = $b_prestamoVentas_directas + $b_apartadosLiquidados_prestamo;
                $b_utilidadVenta = $b_ventasTotales - $b_prestamoVentas;
                $b_costoVentas = $b_prestamoVentas;

                // 4. ABONOS A APARTADOS
                $abonoApartadoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abono_apartado
                    FROM apartado_pagos apg
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                    INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                    WHERE apg.f_cancela IS NULL 
                      AND mo.cod_tipo_movimiento = 8 
                      AND CAST(apg.f_pago AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                
                $b_abonoApartado = (float)($abonoApartadoResult->total_abono_apartado ?? 0);

                // 5. INTERESES COBRADOS
                $interesesResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN mo.cod_tipo_movimiento = 4 THEN mo.monto10 - con.prestamo
                            WHEN mo.cod_tipo_movimiento IN (2, 3) THEN mo.monto10 - COALESCE(ca.abono, 0)
                            ELSE 0
                        END
                    ), 0) AS total_intereses
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                    WHERE mo.cod_tipo_movimiento IN (2,3,4) 
                      AND mo.f_cancela IS NULL 
                      AND con.f_cancelacion IS NULL
                      AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                
                $b_intereses = (float)($interesesResult->total_intereses ?? 0);

                // 6. DESEMPEÑOS
                $desempenosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_desempenos
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 4 
                      AND mo.f_cancela IS NULL 
                      AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                
                $b_desempenos = (float)($desempenosResult->total_desempenos ?? 0);

                // 7. ABONOS A CAPITAL
                $abonoCapitalResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abonos_capital
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento IN (2,3) 
                      AND mo.f_cancela IS NULL 
                      AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                
                $b_abonoCapital = (float)($abonoCapitalResult->total_abonos_capital ?? 0);

                $engancheCreditoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_enganche
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 19
                      AND mo.f_cancela IS NULL 
                      AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                
                $b_engancheCredito = (float)($engancheCreditoResult->total_enganche ?? 0);

                $abonoCreditoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abono_credito
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento IN (20, 21)
                      AND mo.f_cancela IS NULL 
                      AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                
                $b_abonoCredito = (float)($abonoCreditoResult->total_abono_credito ?? 0);

                // Pendiente revisar si existe un tipo de movimiento o tabla para esto.
                $b_certificadoConfianza = 0;

                // 8. EMPEÑOS (Nuevos préstamos)
                $empenosResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        COUNT(DISTINCT con.contrato) as contratos,
                        COALESCE(SUM(mo.monto10), 0) as prestamo
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 1 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL
                    AND CAST(mo.f_alta AS DATETIME) BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                // 9. INVENTARIO y CARTERA
                $inventarioResult = DB::connection($connectionName)->select("
                    SELECT
                        'Alhaja' AS Tipo,
                        CASE 
                            WHEN kilataje BETWEEN 500 AND 999 THEN 'Plata' 
                            WHEN kilataje BETWEEN 8 AND 26 THEN 'Oro' 
                            ELSE 'Varios' 
                        END AS CategoriaMetal,
                        cod_estatus_prenda,
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM alhajas 
                    WHERE cod_estatus_prenda IN (1,9) 
                    GROUP BY CategoriaMetal, cod_estatus_prenda
                    
                    UNION ALL
                    
                    SELECT 
                        'Varios' AS Tipo, 
                        'Varios' AS CategoriaMetal, 
                        cod_estatus_prenda, 
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM varios 
                    WHERE cod_estatus_prenda IN (1,9) 
                    GROUP BY cod_estatus_prenda
                    
                    UNION ALL
                    
                    SELECT 
                        'Auto' AS Tipo, 
                        'Auto' AS CategoriaMetal, 
                        cod_estatus_prenda, 
                        COALESCE(SUM(prestamo), 0) as total_prestamo
                    FROM autos 
                    WHERE cod_estatus_prenda IN (1,9) 
                    GROUP BY cod_estatus_prenda
                ");

                foreach ($inventarioResult as $invRow) {
                    $monto = (float)$invRow->total_prestamo;
                    $b_invTotal += $monto;

                    // Clasificar por estatus para cartera
                    if ($invRow->cod_estatus_prenda == 1) {
                        $b_carteraVigente += $monto; // Prendas en almacén = cartera vigente
                    } elseif ($invRow->cod_estatus_prenda == 9) {
                        $inventarioPisoVentaTotal += $monto; // En piso de venta
                        
                        // Clasificar por tipo para inventario
                        if ($invRow->CategoriaMetal === 'Oro') {
                            $inventarioOro += $monto;
                            $b_ventasRemate += $monto * 0.1; // Estimación de ventas de remate
                        }
                        if ($invRow->Tipo === 'Varios') {
                            $inventarioVarios += $monto;
                        }
                    }

                    // Acumular por categoría
                    if ($invRow->CategoriaMetal === 'Oro') {
                        $invOro += $monto;
                        $b_carteraVencida += $monto * 0.05; // Estimación: 5% vencido
                    }
                    if ($invRow->CategoriaMetal === 'Plata') $invPlata += $monto;
                    if ($invRow->Tipo === 'Varios') $invVarios += $monto;
                    if ($invRow->Tipo === 'Auto') $invAutos += $monto;
                }

                // 10. Balance General (simulado - deberías obtener de tablas reales)
                $balanceResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        (SELECT COALESCE(SUM(prestamo), 0) FROM alhajas WHERE activo = 1) +
                        (SELECT COALESCE(SUM(prestamo), 0) FROM autos WHERE activo = 1) +
                        (SELECT COALESCE(SUM(prestamo), 0) FROM varios WHERE activo = 1) as activo_total,
                        COALESCE(SUM(gas.solicitado), 0) as pasivo_total
                    FROM gastos gas
                    WHERE gas.activo = 1 AND gas.cod_estatus = 2
                ");

                $balanceGeneral['activo_total'] += (float)($balanceResult->activo_total ?? 0);
                $balanceGeneral['pasivo_total'] += (float)($balanceResult->pasivo_total ?? 0);
                $balanceGeneral['capital_total'] = $balanceGeneral['activo_total'] - $balanceGeneral['pasivo_total'];

                // Flujo de efectivo (simulado)
                $ingresosPeriodo = $b_ventas + $b_intereses + $b_desempenos + $b_apartadosLiquidados;
                $egresosPeriodo = $b_egresos;
                $balanceGeneral['efectivo_inicial'] += 100000; // Valor inicial simulado
                $balanceGeneral['efectivo_final'] = $balanceGeneral['efectivo_inicial'] + $ingresosPeriodo - $egresosPeriodo;
                $balanceGeneral['flujo_neto'] = $ingresosPeriodo - $egresosPeriodo;

                // ===== INGRESOS TOTALES (Ventas + Intereses) =====
                $b_ingresosVentasIntereses = $b_ventasTotales + $b_intereses;

                // ===== INGRESOS TOTALES (fórmula completa) =====
                // Alineado a nueva fórmula: Utilidad de Ventas + Intereses + desempeño + venta + abono apartado + abono a capital + enganche de crédito + abono a crédito + certificado de confianza
                $b_ingresos = $b_utilidadVenta + $b_intereses + $b_desempenos + $b_ventas + $b_abonoApartado + $b_abonoCapital + $b_engancheCredito + $b_abonoCredito + $b_certificadoConfianza;

                // ===== UTILIDAD NETA =====
                $b_utilidad = $b_ingresos - $b_egresos;

                // Sumar a Globales
                $totalIngresos += $b_ingresos;
                $totalIngresosVentasIntereses += $b_ingresosVentasIntereses;
                $totalEgresos += $b_egresos;
                $totalGastosOperativos += $b_egresos;

                $empenosData['contratos'] += (int)($empenosResult->contratos ?? 0);
                $empenosData['prestamo'] += (float)($empenosResult->prestamo ?? 0);
                
                // Cartera
                $carteraVigente += $b_carteraVigente;
                $carteraVencida += $b_carteraVencida;
                
                // Ventas por categoría
                $ventasVarios += $b_ventasVarios;
                $ventasOro += $b_ventasOro;
                $ventasRemate += $b_ventasRemate;
                $ventasPlata += $b_ventasPlata;
                $ventasAutos += $b_ventasAutos;
                
                // Transacciones
                $totalTransaccionesVentas += $b_transaccionesVentas;
                $totalContratosApartados += $b_contratosApartados;
                
                // Acumular conceptos
                $utilidadVentaGlobal += $b_utilidadVenta;
                $interesesGlobal += $b_intereses;
                $desempenosGlobal += $b_desempenos;
                $ventasGlobal += $b_ventas;
                $apartadosLiquidadosGlobal += $b_apartadosLiquidados;
                $abonoApartadoGlobal += $b_abonoApartado;
                $abonoCapitalGlobal += $b_abonoCapital;
                $engancheCreditoGlobal += $b_engancheCredito;
                $abonoCreditoGlobal += $b_abonoCredito;
                $certificadoConfianzaGlobal += $b_certificadoConfianza;
                
                $ventasTotalesGlobal += $b_ventasTotales;
                $prestamoVentasGlobal += $b_prestamoVentas;
                $costoVentasGlobal += $b_costoVentas;

                // Obtener meta de la sucursal
                $meta = $metasSucursales[$sucursal->id_valora_mas] ?? [
                    'meta_ingresos' => 1000000,
                    'meta_ventas' => 800000,
                    'meta_utilidad' => 200000
                ];

                // Determinar semáforo de desempeño
                $cumplimientoIngresos = $b_ingresos > 0 ? ($b_ingresos / $meta['meta_ingresos']) * 100 : 0;
                
                if ($cumplimientoIngresos >= 90) {
                    $semaforo = 'verde';
                } elseif ($cumplimientoIngresos >= 70) {
                    $semaforo = 'amarillo';
                } else {
                    $semaforo = 'rojo';
                }

                // Armar KPI de Sucursal
                $margenBruto = $b_ingresos > 0 ? ($b_utilidad / $b_ingresos) * 100 : 0;

                $branchKPIs[$sucursal->nombre] = [
                    'id' => $sucursal->id_valora_mas,
                    'ingresos' => $b_ingresos,
                    'ingresos_ventas_intereses' => $b_ingresosVentasIntereses,
                    'gastos' => $b_egresos,
                    'utilidad_neta' => $b_utilidad,
                    'margen_bruto_pct' => round($margenBruto, 2),
                    'inventario_total' => $b_invTotal,
                    'inventario_varios' => $inventarioVarios,
                    'inventario_oro' => $inventarioOro,
                    'cartera_vigente' => $b_carteraVigente,
                    'cartera_vencida' => $b_carteraVencida,
                    'ventas_varios' => $b_ventasVarios,
                    'ventas_oro' => $b_ventasOro,
                    'ventas_remate' => $b_ventasRemate,
                    'semaforo' => $semaforo,
                    'cumplimiento' => round($cumplimientoIngresos, 1),
                    'meta_ingresos' => $meta['meta_ingresos'],
                    'detalle' => [
                        'utilidad_venta' => $b_utilidadVenta,
                        'intereses' => $b_intereses,
                        'desempenos' => $b_desempenos,
                        'ventas' => $b_ventas,
                        'apartados_liquidados' => $b_apartadosLiquidados,
                        'abono_apartado' => $b_abonoApartado,
                        'abono_capital' => $b_abonoCapital,
                        'enganche_credito' => $b_engancheCredito,
                        'abono_credito' => $b_abonoCredito,
                        'certificado_confianza' => $b_certificadoConfianza,
                        'ventas_totales' => $b_ventasTotales,
                        'prestamo_ventas' => $b_prestamoVentas,
                        'costo_ventas' => $b_costoVentas,
                        'transacciones_ventas' => $b_transaccionesVentas, 
                        'contratos_apartados' => $b_contratosApartados, 
                    ]
                ];

            } catch (\Exception $e) {
                Log::error("Error procesando sucursal {$sucursal->nombre} ({$dbName}): " . $e->getMessage());
                continue;
            }
        }

        // Calcular métricas globales
        $utilidadBruta = $totalIngresos - $costoVentasGlobal;
        $margenBrutoPorcentaje = $totalIngresos > 0 ? round(($utilidadBruta / $totalIngresos) * 100, 2) : 0;
        $utilidadOperativa = $utilidadBruta - $totalGastosOperativos;
        $utilidadNetaConsolidada = $totalIngresos - $totalEgresos;
        $margenNetoConsolidado = $totalIngresos > 0 ? round(($utilidadNetaConsolidada / $totalIngresos) * 100, 2) : 0;

        // Calcular totales de inventario
        $inventarioVariosTotal = $invVarios;
        $inventarioOroTotal = $invOro;

        // Gráficos
        $chartFinanciero = [
            'labels' => ['Ingresos', 'Gastos Operativos', 'Utilidad Neta'],
            'data' => [$totalIngresos, $totalGastosOperativos, $utilidadNetaConsolidada]
        ];
        
        $chartUtilidades = [
            'labels' => ['Utilidad Bruta', 'Utilidad Operativa', 'Utilidad Neta'],
            'data' => [$utilidadBruta, $utilidadOperativa, $utilidadNetaConsolidada]
        ];

        $chartInventario = [
            'labels' => ['Oro', 'Plata', 'Varios', 'Autos'],
            'data' => [$invOro, $invPlata, $invVarios, $invAutos]
        ];

        $chartVentasCategoria = [
            'labels' => ['Oro', 'Plata', 'Varios', 'Autos', 'Remate'],
            'data' => [$ventasOro, $ventasPlata, $ventasVarios, $ventasAutos, $ventasRemate]
        ];

        $chartCartera = [
            'labels' => ['Vigente', 'Vencida'],
            'data' => [$carteraVigente, $carteraVencida]
        ];

        $chartSucursales = $this->prepareBranchChartData($branchKPIs);

        return response()->json([
            // Métricas básicas
            'totalIngresos' => $totalIngresos,
            'totalIngresosVentasIntereses' => $totalIngresosVentasIntereses,
            'totalEgresos' => $totalEgresos,
            'gastosOperativos' => $totalGastosOperativos,
            
            // Nuevas métricas solicitadas
            'utilidadBruta' => $utilidadBruta,
            'margenBrutoPorcentaje' => $margenBrutoPorcentaje,
            'utilidadOperativa' => $utilidadOperativa,
            'utilidadNetaConsolidada' => $utilidadNetaConsolidada,
            'margenNetoConsolidado' => $margenNetoConsolidado,
            'costoVentas' => $costoVentasGlobal,
            
            // Cartera de empeño
            'carteraVigente' => $carteraVigente,
            'carteraVencida' => $carteraVencida,
            'carteraTotal' => $carteraVigente + $carteraVencida,
            'tasaMora' => ($carteraVigente + $carteraVencida) > 0 ? 
                          round(($carteraVencida / ($carteraVigente + $carteraVencida)) * 100, 2) : 0,
            
            // Inventario
            'inventarioPisoVentaTotal' => $inventarioPisoVentaTotal,
            'inventarioVarios' => $inventarioVariosTotal,
            'inventarioOro' => $inventarioOroTotal,
            'invOro' => $invOro,
            'invPlata' => $invPlata,
            'invVarios' => $invVarios,
            'invAutos' => $invAutos,
            
            // Ventas por categoría
            'ventasVarios' => $ventasVarios,
            'ventasOro' => $ventasOro,
            'ventasRemate' => $ventasRemate,
            'ventasPlata' => $ventasPlata,
            'ventasAutos' => $ventasAutos,
            'ventasTotales' => $ventasTotalesGlobal,
            
            // Indicadores financieros clave
            'balanceGeneral' => $balanceGeneral,
            'liquidez' => $balanceGeneral['pasivo_total'] > 0 ? 
                          round($balanceGeneral['activo_total'] / $balanceGeneral['pasivo_total'], 2) : 0,
            'rentabilidad' => $totalIngresos > 0 ? 
                             round(($utilidadNetaConsolidada / $totalIngresos) * 100, 2) : 0,
            
            // Empeños
            'empenosData' => $empenosData,
            
            // Transacciones
            'transaccionesVentas' => $totalTransaccionesVentas,
            'contratosApartados' => $totalContratosApartados,
            
            // Detalle de ingresos
            'detalleIngresos' => [
                'utilidad_venta' => $utilidadVentaGlobal,
                'intereses' => $interesesGlobal,
                'desempenos' => $desempenosGlobal,
                'ventas' => $ventasGlobal,
                'apartados_liquidados' => $apartadosLiquidadosGlobal,
                'abono_apartado' => $abonoApartadoGlobal,
                'abono_capital' => $abonoCapitalGlobal,
                'enganche_credito' => $engancheCreditoGlobal,
                'abono_credito' => $abonoCreditoGlobal,
                'certificado_confianza' => $certificadoConfianzaGlobal,
            ],
            
            // Datos para utilidad neta
            'prestamoVentas' => $prestamoVentasGlobal,
            
            // KPIs por sucursal
            'branchKPIs' => $branchKPIs,
            
            // Gráficos
            'chartFinanciero' => $chartFinanciero,
            'chartUtilidades' => $chartUtilidades,
            'chartInventario' => $chartInventario,
            'chartVentasCategoria' => $chartVentasCategoria,
            'chartCartera' => $chartCartera,
            'chartSucursales' => $chartSucursales
        ]);
    }

    private function prepareBranchChartData($branchKPIs)
    {
        $labels = array_keys($branchKPIs);
        $ingresos = [];
        $utilidades = [];
        $cumplimientos = [];
        $semaforos = [];
        $margenes = [];

        foreach ($branchKPIs as $kpi) {
            $ingresos[] = $kpi['ingresos'];
            $utilidades[] = $kpi['utilidad_neta'];
            $cumplimientos[] = $kpi['cumplimiento'] ?? 0;
            $semaforos[] = $kpi['semaforo'] ?? 'amarillo';
            $margenes[] = $kpi['margen_bruto_pct'] ?? 0;
        }

        return [
            'labels' => $labels,
            'ingresos' => $ingresos,
            'utilidades' => $utilidades,
            'cumplimientos' => $cumplimientos,
            'semaforos' => $semaforos,
            'margenes' => $margenes
        ];
    }

    private function getMetasSucursales()
    {
        // Esto debería venir de una tabla de metas en la base de datos
        // Por ahora devolvemos valores de ejemplo
        return [
            1 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            2 => ['meta_ingresos' => 1500000, 'meta_ventas' => 1200000, 'meta_utilidad' => 300000],
            3 => ['meta_ingresos' => 800000, 'meta_ventas' => 600000, 'meta_utilidad' => 150000],
        ];
    }
}