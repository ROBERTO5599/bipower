<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class ResumenEjecutivoController extends Controller
{
    public function index(Request $request)
    {
        //  MODIFICACIÓN: Filtrar solo sucursales que existen
        $idsQueFuncionan = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19];
        $sucursales = Sucursal::whereNotNull('id_valora_mas')
            ->whereIn('id_valora_mas', $idsQueFuncionan)
            ->get();

        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();

        return view('resumen-ejecutivo.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

   
    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';

        $fechaFinSiguiente = $fechaFin;

        // MODIFICACIÓN: IDs de sucursales que existen
        $idsQueFuncionan = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19];
        
        // MODIFICACIÓN: Filtrar directamente en la consulta
        $sucursales = Sucursal::whereNotNull('id_valora_mas')
            ->whereIn('id_valora_mas', $idsQueFuncionan)
            ->get();
        
        $sucursalId = $request->input('sucursal_id');

        if ($sucursalId && in_array((int)$sucursalId, $idsQueFuncionan)) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');

        // Variables Globales
        $totalIngresos = 0;
        $totalIngresosVentasIntereses = 0;
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
        $liquidacionCreditoGlobal = 0; 
        $certificadoConfianzaGlobal = 0;
        $utilidadCreditosGlobal = 0;

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

        // Metas por sucursal
        $metasSucursales = $this->getMetasSucursales();

        $branchKPIs = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi_' . $sucursal->id_valora_mas;

            $b_ingresos = 0;
            $b_ingresosVentasIntereses = 0;
            $b_egresos = 0;
            $b_utilidadBruta = 0;
            $b_utilidadNeta = 0;
            $b_invTotal = 0;

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
            $b_liquidacionCredito = 0;
            $b_certificadoConfianza = 0;
            $b_utilidadCreditos = 0;

            $b_transaccionesVentas = 0;
            $b_contratosApartados = 0;
            $b_costoVentas = 0;

            $b_carteraVigente = 0;
            $b_carteraVencida = 0;

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

                // ============================================
                // 1. GASTOS (Egresos)
                // ============================================
                $gastosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN gas.cod_estatus = 5 THEN 0 
                            ELSE gas.solicitado 
                        END
                    ), 0) AS TotalGastos
                    FROM gastos gas
                    INNER JOIN movimientos mov ON gas.cod_movimiento = mov.cod_movimiento
                    WHERE gas.activo = 1
                    AND gas.f_solicitado BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_egresos = (float) ($gastosResult->TotalGastos ?? 0);

                // ============================================
                // 2. VENTAS (movimientos 5 y 6)
                // ============================================
                $ventasResult = DB::connection($connectionName)->selectOne("
                   SELECT 
                        COALESCE(SUM(mo.monto10), 0) AS total_ventas, 
                        COUNT(DISTINCT mo.cod_movimiento) AS total_transacciones
                    FROM movimientos mo
                    INNER JOIN ventas ve ON ve.cod_movimiento = mo.cod_movimiento  
                    WHERE mo.cod_estatus = 2 
                    AND mo.cod_tipo_movimiento IN (5, 6)
                    AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_ventas = (float) ($ventasResult->total_ventas ?? 0);
                $b_transaccionesVentas = (int) ($ventasResult->total_transacciones ?? 0);

                // ============================================
                // 3. LIQUIDACION DE APARTADOS (movimiento 12)
                // ============================================
                $apartadosLiquidadosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_apartados_liquidados
                    FROM apartado_pagos apg 
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                    WHERE apg.f_cancela IS NULL 
                      AND mo.cod_tipo_movimiento = 12
                      AND apg.f_pago BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_apartadosLiquidados = (float) ($apartadosLiquidadosResult->total_apartados_liquidados ?? 0);

                // Contar contratos de apartados liquidados
                $contratosApartadosResult = DB::connection($connectionName)->selectOne("
                    SELECT COUNT(DISTINCT ap.cod_apartado) AS total_contratos
                    FROM apartado_pagos apg
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                    WHERE apg.f_cancela IS NULL 
                      AND mo.cod_tipo_movimiento = 12 
                      AND apg.f_pago BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_contratosApartados = (int) ($contratosApartadosResult->total_contratos ?? 0);

                // ============================================
                // 4. ABONO APARTADO (movimiento 8)
                // ============================================
                $abonoApartadoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abono_apartado
                    FROM apartado_pagos apg 
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                    WHERE apg.f_cancela IS NULL 
                      AND mo.cod_tipo_movimiento = 8
                      AND apg.f_pago BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_abonoApartado = (float) ($abonoApartadoResult->total_abono_apartado ?? 0);

                // ============================================
                // 5. ABONO A CAPITAL PENDIENTE
                // ============================================
                $abonoCapitalResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(COALESCE(ca.abono, 0)), 0) AS total_abono_capital
                    FROM movimientos mo 
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    LEFT JOIN contratos ca ON ca.cod_contrato = con.cod_anterior
                    WHERE mo.cod_tipo_movimiento IN (2,3)
                      AND mo.f_cancela IS NULL
                      AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_abonoCapital = (float) ($abonoCapitalResult->total_abono_capital ?? 0);

                // ============================================
                // 6. INTERESES (CÁLCULO CORREGIDO)
                // ============================================
                $interesesResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(
                        CASE 
                            WHEN mo.cod_tipo_movimiento = 2 THEN IF(mo.monto10 < 20, 20.0, mo.monto10)
                            WHEN mo.cod_tipo_movimiento = 4 THEN mo.monto10 - con.prestamo
                            WHEN mo.cod_tipo_movimiento = 3 THEN mo.monto10 - COALESCE((SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior), 0)
                            ELSE 0 
                        END
                    ), 0) AS total_intereses
                    FROM movimientos mo 
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE con.f_cancelacion IS NULL AND con.cod_tipo_prenda IN (1, 2, 3)
                    AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                    AND mo.cod_tipo_movimiento IN (2, 3, 4)
                ", [
                    ':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente
                ]);

                $b_intereses = (float) ($interesesResult->total_intereses ?? 0);

                // ============================================
                // 7. DESEMPEÑO
                // ============================================
                $desempenosResult = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COUNT(*) AS total_registros,
                        SUM(prestamo) AS suma_prestamos
                    FROM (
                        SELECT al.prestamo
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
                        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
                        WHERE con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 1 
                        AND mo.f_alta BETWEEN :fechaDel1 AND :fechaAlSig1
                        AND mo.cod_tipo_movimiento IN (4)

                        UNION ALL 

                        SELECT au.prestamo
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                        INNER JOIN marcas ma ON ma.cod_marca = au.cod_marca AND ma.cod_tipo_prenda = 2
                        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario 
                        WHERE con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 2 
                        AND mo.f_alta BETWEEN :fechaDel2 AND :fechaAlSig2
                        AND mo.cod_tipo_movimiento IN (4)
                        UNION ALL
                        SELECT va.prestamo
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
                        INNER JOIN marcas ma ON ma.cod_marca = va.cod_marca AND ma.cod_tipo_prenda = 3
                        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
                        WHERE con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 3 
                        AND mo.f_alta BETWEEN :fechaDel3 AND :fechaAlSig3
                        AND mo.cod_tipo_movimiento IN (4)

                    ) AS reporte_general;
                ", [
                    ':fechaDel1' => $fechaInicio, ':fechaAlSig1' => $fechaFinSiguiente,
                    ':fechaDel2' => $fechaInicio, ':fechaAlSig2' => $fechaFinSiguiente,
                    ':fechaDel3' => $fechaInicio, ':fechaAlSig3' => $fechaFinSiguiente
                ]);

                $b_desempenos = (float) ($desempenosResult->suma_prestamos ?? 0);

                // ============================================
                // 8. ENGANCHE CREDITO
                // ============================================
                $engancheCreditoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_enganche_credito
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 19
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_engancheCredito = (float) ($engancheCreditoResult->total_enganche_credito ?? 0);

                // ============================================
                // 9. PAGO CREDITO
                // ============================================
                $abonoCreditoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abono_credito
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento IN (20)
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_abonoCredito = (float) ($abonoCreditoResult->total_abono_credito ?? 0);

                // ============================================
                // 10. LIQUIDACION CREDITO
                // ============================================
                $liquidacionCreditoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_liquidacion_credito
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 21
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_liquidacionCredito = (float) ($liquidacionCreditoResult->total_liquidacion_credito ?? 0);

                // ============================================
                // 11. CERTIFICADO CONFIANZA
                // ============================================
                $certificadoConfianzaResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(ga.monto_garantia), 0) AS total_monto
                    FROM garantias ga
                    WHERE ga.f_alta BETWEEN :fechaDel AND :fechaAlSig
                      AND ga.f_cancelacion IS NULL
                      AND ga.cod_estatus <> 3
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_certificadoConfianza = (float) ($certificadoConfianzaResult->total_monto ?? 0);

                // ============================================
                // 12. EMPEÑOS
                // ============================================
                $empenosResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        COUNT(DISTINCT con.contrato) as contratos,
                        COALESCE(SUM(mo.monto10), 0) as prestamo
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 1 
                      AND mo.f_cancela IS NULL 
                      AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                // ============================================
                // 13. VENTAS POR CATEGORÍA
                // ============================================
                $ventasCategoriaResult = DB::connection($connectionName)->select("
                    SELECT
                        ve.cod_tipo_prenda,
                        SUM(dv.venta10) as total_venta,
                        COUNT(DISTINCT ve.cod_venta) as transacciones
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    WHERE ve.f_cancela IS NULL 
                      AND ve.f_venta BETWEEN :fechaDel AND :fechaAlSig
                    GROUP BY ve.cod_tipo_prenda
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                foreach ($ventasCategoriaResult as $venta) {
                    $monto = (float) $venta->total_venta;
                    switch ($venta->cod_tipo_prenda) {
                        case 1:
                            $b_ventasOro += $monto;
                            break;
                        case 2:
                            $b_ventasAutos += $monto;
                            break;
                        case 3:
                            $b_ventasVarios += $monto;
                            break;
                    }
                }

                // ============================================
                // 14. INVENTARIO Y CARTERA
                // ============================================
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
                    $monto = (float) $invRow->total_prestamo;
                    $b_invTotal += $monto;

                    if ($invRow->cod_estatus_prenda == 1) {
                        $b_carteraVigente += $monto;
                    } elseif ($invRow->cod_estatus_prenda == 9) {
                        $inventarioPisoVentaTotal += $monto;
                        if ($invRow->CategoriaMetal === 'Oro') {
                            $inventarioOro += $monto;
                        }
                        if ($invRow->Tipo === 'Varios') {
                            $inventarioVarios += $monto;
                        }
                    }

                    if ($invRow->CategoriaMetal === 'Oro') {
                        $invOro += $monto;
                        $b_carteraVencida += $monto * 0.05;
                    }
                    if ($invRow->CategoriaMetal === 'Plata')
                        $invPlata += $monto;
                    if ($invRow->Tipo === 'Varios')
                        $invVarios += $monto;
                    if ($invRow->Tipo === 'Auto')
                        $invAutos += $monto;
                }

                // Estimaciones (después de tener ventas reales)
                $b_ventasPlata = $b_ventasOro * 0.3;
                $b_ventasRemate = $b_ventas; // Ventas de piso de venta son remates, usando el valor real de ventas

                // ============================================
                // 15. CÁLCULO DE UTILIDAD DE VENTA (CORREGIDO)
                // ============================================
                
                // 15.1 Préstamo de ventas directas (sin duplicar)
                $prestamoVentasResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(
                        CASE
                            WHEN ve.cod_tipo_prenda = 1 THEN COALESCE((SELECT prestamo FROM alhajas WHERE cod_alhaja = dv.cod_prenda), 0)
                            WHEN ve.cod_tipo_prenda = 2 THEN COALESCE((SELECT prestamo FROM autos WHERE cod_auto = dv.cod_prenda), 0)
                            WHEN ve.cod_tipo_prenda = 3 THEN COALESCE((SELECT prestamo FROM varios WHERE cod_varios = dv.cod_prenda), 0)
                            ELSE 0
                        END
                    ), 0) as prestamo_ventas
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    WHERE ve.f_cancela IS NULL 
                      AND ve.f_venta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_prestamoVentas = (float) ($prestamoVentasResult->prestamo_ventas ?? 0);
                
                // 15.2 Préstamo de apartados liquidados
                $prestamoApartadosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(
                        CASE
                            WHEN ap.cod_tipo_prenda = 1 THEN COALESCE((SELECT prestamo FROM alhajas WHERE cod_alhaja = da.cod_prenda), 0)
                            WHEN ap.cod_tipo_prenda = 2 THEN COALESCE((SELECT prestamo FROM autos WHERE cod_auto = da.cod_prenda), 0)
                            WHEN ap.cod_tipo_prenda = 3 THEN COALESCE((SELECT prestamo FROM varios WHERE cod_varios = da.cod_prenda), 0)
                            ELSE 0
                        END
                    ), 0) AS prestamo_apartados
                    FROM apartado_pagos apg
                    INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
                    INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
                    INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
                    WHERE apg.f_cancela IS NULL 
                      AND mo.cod_tipo_movimiento = 12 
                      AND apg.f_pago BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);

                $b_prestamoApartados = (float) ($prestamoApartadosResult->prestamo_apartados ?? 0);

                // 15.3 TOTALES CORREGIDOS (evitando duplicación)
                // NOTA: b_ventas y b_apartadosLiquidados ya suman ventas únicas
                $b_prestamoVentasTotal = $b_prestamoVentas + $b_prestamoApartados;
                $b_ventasTotales = $b_ventas + $b_apartadosLiquidados;
                
                // Utilidad de venta = Precio de venta - Costo (préstamo original)
                $b_utilidadVenta = $b_ventasTotales - $b_prestamoVentasTotal;
                $b_costoVentas = $b_prestamoVentasTotal;

                // ============================================
                // 15.2 UTILIDAD DE CRÉDITO (CORREGIDO)
                // ============================================
                $utilidadCreditosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10 - COALESCE(cap.prestamo, 0)), 0) AS total_utilidad_creditos
                    FROM movimientos mo
                    LEFT JOIN creditos cre ON cre.cod_credito = mo.cod_contrato
                    LEFT JOIN varios cap ON cap.cod_varios = cre.cod_varios
                    WHERE mo.cod_tipo_movimiento = 21
                      AND mo.f_cancela IS NULL
                      AND mo.cod_estatus IN (1, 2)
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAlSig
                ", [':fechaDel' => $fechaInicio, ':fechaAlSig' => $fechaFinSiguiente]);
                
                $b_utilidadCreditos = (float) ($utilidadCreditosResult->total_utilidad_creditos ?? 0);

                // ============================================
                // 16. INGRESOS TOTALES (CORREGIDO)
                // ============================================
                $b_ingresos = $b_ventas + $b_apartadosLiquidados + $b_abonoApartado + $b_abonoCapital + 
                              $b_intereses + $b_desempenos + $b_engancheCredito + $b_abonoCredito + 
                              $b_liquidacionCredito + $b_certificadoConfianza;

                $b_ingresosVentasIntereses = $b_ventasTotales + $b_intereses;

                // ============================================
                // 17. UTILIDAD BRUTA (FÓRMULA ACTUALIZADA)
                // ============================================
                // Fórmula: Utilidad de Ventas + Intereses + Utilidad de Crédito Liquidado + Ventas de Certificados
                $b_utilidadBruta = $b_utilidadVenta + $b_intereses + $b_utilidadCreditos + $b_certificadoConfianza;
                
                // VALIDACIÓN: Si la utilidad bruta es negativa con ingresos positivos, hay error
                if ($b_utilidadBruta < 0 && $b_ingresos > 0) {
                    Log::warning("UTILIDAD BRUTA NEGATIVA DETECTADA en {$sucursal->nombre}", [
                        'ingresos' => $b_ingresos,
                        'utilidad_bruta' => $b_utilidadBruta,
                        'intereses' => $b_intereses,
                        'utilidad_venta' => $b_utilidadVenta,
                        'ventas_totales' => $b_ventasTotales,
                        'prestamo_ventas_total' => $b_prestamoVentasTotal,
                        'costo_ventas' => $b_costoVentas
                    ]);
                }

                // ============================================
                // 18. UTILIDAD NETA
                // ============================================
                $b_utilidadNeta = $b_ingresos - $b_egresos;

                // ============================================
                // 19. BALANCE GENERAL
                // ============================================
                $balanceResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        (SELECT COALESCE(SUM(prestamo), 0) FROM alhajas WHERE activo = 1) +
                        (SELECT COALESCE(SUM(prestamo), 0) FROM autos WHERE activo = 1) +
                        (SELECT COALESCE(SUM(prestamo), 0) FROM varios WHERE activo = 1) as activo_total,
                        COALESCE(SUM(gas.solicitado), 0) as pasivo_total
                    FROM gastos gas
                    WHERE gas.activo = 1 AND gas.cod_estatus = 2
                ");

                $balanceGeneral['activo_total'] += (float) ($balanceResult->activo_total ?? 0);
                $balanceGeneral['pasivo_total'] += (float) ($balanceResult->pasivo_total ?? 0);
                $balanceGeneral['capital_total'] = $balanceGeneral['activo_total'] - $balanceGeneral['pasivo_total'];

                // Flujo de efectivo
                $balanceGeneral['efectivo_inicial'] += 100000;
                $balanceGeneral['efectivo_final'] = $balanceGeneral['efectivo_inicial'] + $b_ingresos - $b_egresos;
                $balanceGeneral['flujo_neto'] = $b_ingresos - $b_egresos;

                // ============================================
                // 20. ACUMULAR GLOBALES
                // ============================================
                $totalIngresos += $b_ingresos;
                $totalIngresosVentasIntereses += $b_ingresosVentasIntereses;
                $totalEgresos += $b_egresos;
                $totalGastosOperativos += $b_egresos;

                $empenosData['contratos'] += (int) ($empenosResult->contratos ?? 0);
                $empenosData['prestamo'] += (float) ($empenosResult->prestamo ?? 0);

                $carteraVigente += $b_carteraVigente;
                $carteraVencida += $b_carteraVencida;

                $ventasVarios += $b_ventasVarios;
                $ventasOro += $b_ventasOro;
                $ventasRemate += $b_ventasRemate;
                $ventasPlata += $b_ventasPlata;
                $ventasAutos += $b_ventasAutos;

                $totalTransaccionesVentas += $b_transaccionesVentas;
                $totalContratosApartados += $b_contratosApartados;

                $utilidadVentaGlobal += $b_utilidadVenta;
                $interesesGlobal += $b_intereses;
                $desempenosGlobal += $b_desempenos;
                $ventasGlobal += $b_ventas;
                $apartadosLiquidadosGlobal += $b_apartadosLiquidados;
                $abonoApartadoGlobal += $b_abonoApartado;
                $abonoCapitalGlobal += $b_abonoCapital;
                $engancheCreditoGlobal += $b_engancheCredito;
                $abonoCreditoGlobal += $b_abonoCredito;
                $liquidacionCreditoGlobal += $b_liquidacionCredito; 
                $certificadoConfianzaGlobal += $b_certificadoConfianza;
                $utilidadCreditosGlobal += $b_utilidadCreditos;

                $ventasTotalesGlobal += $b_ventasTotales;
                $prestamoVentasGlobal += $b_prestamoVentasTotal;
                $costoVentasGlobal += $b_costoVentas;

                // ============================================
                // 21. METAS Y SEMÁFORO
                // ============================================
                $meta = $metasSucursales[$sucursal->id_valora_mas] ?? [
                    'meta_ingresos' => 1000000,
                    'meta_ventas' => 800000,
                    'meta_utilidad' => 200000
                ];

                $cumplimientoIngresos = $b_ingresos > 0 ? ($b_ingresos / $meta['meta_ingresos']) * 100 : 0;

                if ($cumplimientoIngresos >= 90) {
                    $semaforo = 'verde';
                } elseif ($cumplimientoIngresos >= 70) {
                    $semaforo = 'amarillo';
                } else {
                    $semaforo = 'rojo';
                }

                $margenBruto = $b_ingresos > 0 ? ($b_utilidadBruta / $b_ingresos) * 100 : 0;

                // ============================================
                // 22. KPI POR SUCURSAL
                // ============================================
                $branchKPIs[$sucursal->nombre] = [
                    'id' => $sucursal->id_valora_mas,
                    'ingresos' => $b_ingresos,
                    'ingresos_ventas_intereses' => $b_ingresosVentasIntereses,
                    'gastos' => $b_egresos,
                    'utilidad_bruta' => $b_utilidadBruta,
                    'utilidad_neta' => $b_utilidadNeta,
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
                        'liquidacion_credito' => $b_liquidacionCredito,
                        'certificado_confianza' => $b_certificadoConfianza,
                        'utilidad_creditos' => $b_utilidadCreditos,
                        'ventas_totales' => $b_ventasTotales,
                        'prestamo_ventas' => $b_prestamoVentasTotal,
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

        // ============================================
        // 23. CÁLCULOS FINALES
        // ============================================
        $utilidadBruta = $utilidadVentaGlobal + $interesesGlobal + $utilidadCreditosGlobal + $certificadoConfianzaGlobal;
        $margenBrutoPorcentaje = $totalIngresos > 0 ? round(($utilidadBruta / $totalIngresos) * 100, 2) : 0;
        $utilidadOperativa = $utilidadBruta - $totalGastosOperativos;
        $utilidadNetaConsolidada = $totalIngresos - $totalEgresos;
        $margenNetoConsolidado = $totalIngresos > 0 ? round(($utilidadNetaConsolidada / $totalIngresos) * 100, 2) : 0;

        // ============================================
        // 24. GRÁFICOS
        // ============================================
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

        // ============================================
        // 25. RESPUESTA JSON
        // ============================================
        return response()->json([
            'totalIngresos' => $totalIngresos,
            'totalIngresosVentasIntereses' => $totalIngresosVentasIntereses,
            'totalEgresos' => $totalEgresos,
            'gastosOperativos' => $totalGastosOperativos,
            'utilidadBruta' => $utilidadBruta,
            'margenBrutoPorcentaje' => $margenBrutoPorcentaje,
            'utilidadOperativa' => $utilidadOperativa,
            'utilidadNetaConsolidada' => $utilidadNetaConsolidada,
            'margenNetoConsolidado' => $margenNetoConsolidado,
            'costoVentas' => $costoVentasGlobal,
            'carteraVigente' => $carteraVigente,
            'carteraVencida' => $carteraVencida,
            'carteraTotal' => $carteraVigente + $carteraVencida,
            'tasaMora' => ($carteraVigente + $carteraVencida) > 0 ? round(($carteraVencida / ($carteraVigente + $carteraVencida)) * 100, 2) : 0,
            'inventarioPisoVentaTotal' => $inventarioPisoVentaTotal,
            'inventarioVarios' => $inventarioVarios,
            'inventarioOro' => $inventarioOro,
            'invOro' => $invOro,
            'invPlata' => $invPlata,
            'invVarios' => $invVarios,
            'invAutos' => $invAutos,
            'ventasVarios' => $ventasVarios,
            'ventasOro' => $ventasOro,
            'ventasRemate' => $ventasRemate,
            'ventasPlata' => $ventasPlata,
            'ventasAutos' => $ventasAutos,
            'ventasTotales' => $ventasTotalesGlobal,
            'balanceGeneral' => $balanceGeneral,
            'liquidez' => $balanceGeneral['pasivo_total'] > 0 ? round($balanceGeneral['activo_total'] / $balanceGeneral['pasivo_total'], 2) : 0,
            'rentabilidad' => $totalIngresos > 0 ? round(($utilidadNetaConsolidada / $totalIngresos) * 100, 2) : 0,
            'empenosData' => $empenosData,
            'transaccionesVentas' => $totalTransaccionesVentas,
            'contratosApartados' => $totalContratosApartados,
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
                'liquidacion_credito' => $liquidacionCreditoGlobal,
                'certificado_confianza' => $certificadoConfianzaGlobal,
                'utilidad_creditos' => $utilidadCreditosGlobal,
            ],
            'prestamoVentas' => $prestamoVentasGlobal,
            'branchKPIs' => $branchKPIs,
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
        // MODIFICACIÓN: Metas solo para sucursales que existen
        return [
            2 => ['meta_ingresos' => 1500000, 'meta_ventas' => 1200000, 'meta_utilidad' => 300000],
            4 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            6 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            8 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            10 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            11 => ['meta_ingresos' => 500000, 'meta_ventas' => 400000, 'meta_utilidad' => 100000],
            13 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            15 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            16 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            17 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            18 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            19 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
        ];
    }
}