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
        $fechaInicio = now()->startOfMonth()->toDateString() . ' 00:00:00';
        $fechaFin = now()->toDateString() . ' 23:59:59';
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('resumen-ejecutivo.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    // Endpoint AJAX para obtener los datos agregados
    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
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

        // Nuevos acumuladores para Reporte Detallado
        $rd_refrendos_total = 0;
        $rd_desempenos_interes = 0;
        $rd_abono_capital_total = 0;
        $rd_abono_capital_interes = 0;
        $rd_creditos_total = 0;
        $rd_apartados_total = 0;
        $rd_empenos_contratos = 0;
        $rd_empenos_prestamo = 0;
        $rd_ventas_total = 0;
        $rd_abono_apartado_total = 0;
        $rd_garantias_total = 0;

        // Inventario Detallado (para tabla consolidada)
        $rd_inv = [
            'piso' => ['Oro' => ['cant' => 0, 'monto' => 0], 'Plata' => ['cant' => 0, 'monto' => 0], 'Varios' => ['cant' => 0, 'monto' => 0], 'Auto' => ['cant' => 0, 'monto' => 0], 'total_cant' => 0, 'total_monto' => 0],
            'depositaria' => ['Oro' => ['cant' => 0, 'monto' => 0], 'Plata' => ['cant' => 0, 'monto' => 0], 'Varios' => ['cant' => 0, 'monto' => 0], 'Auto' => ['cant' => 0, 'monto' => 0], 'total_cant' => 0, 'total_monto' => 0],
        ];

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

        // Metas por sucursal
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
            $b_intereses_refrendo = 0;
            $b_intereses_desempeno = 0;

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

                $b_egresos = (float) ($gastosResult->TotalGastos ?? 0);

                // 2. VENTAS (5,6) - CORREGIDO: usa mo.monto10 directamente
                $ventasResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_ventas
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento IN (5, 6) 
                      AND mo.f_cancela IS NULL 
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_ventas = (float) ($ventasResult->total_ventas ?? 0);
                $b_transaccionesVentas = 0; // Ya no calculamos transacciones aquí

                // 3. APARTADOS LIQUIDADOS (12) - CORREGIDO: usa mo.monto10 directamente
                $apartadosLiquidadosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_apartados_precio
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 12 
                      AND mo.f_cancela IS NULL 
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_apartadosLiquidados = (float) ($apartadosLiquidadosResult->total_apartados_precio ?? 0);
                $b_contratosApartados = 0; // Ya no calculamos contratos aquí
                $b_apartadosLiquidados_prestamo = 0;

                // 4. ABONOS A APARTADOS (7,8)
                $abonoApartadoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abono_apartado
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento IN (7,8) 
                      AND mo.f_cancela IS NULL 
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_abonoApartado = (float) ($abonoApartadoResult->total_abono_apartado ?? 0);

                // 5. INTERESES - REFRENDO (tipo 2)
                $interesesRefrendoRes = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 2 AND mo.f_cancela IS NULL 
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                $b_intereses_refrendo = (float) ($interesesRefrendoRes->total ?? 0);

                // 6. INTERESES DESEMPEÑO (tipo 4 - solo interés)
                $interesesDesempenoRes = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10 - con.prestamo), 0) AS total
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 4 AND mo.f_cancela IS NULL 
                      AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);
                $b_intereses_desempeno = (float) ($interesesDesempenoRes->total ?? 0);

                // 7. ABONO CAPITAL (tipo 3 - CAPITAL e INTERÉS)
                $abonoCapitalResult = DB::connection($connectionName)->selectOne("
                    SELECT 
                        COALESCE(SUM(mo.abono_c), 0) AS capital_abonado,
                        COALESCE(SUM(mo.monto10 - mo.abono_c), 0) AS interes_cobrado
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 3 
                      AND mo.f_cancela IS NULL 
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_abonoCapital = (float) ($abonoCapitalResult->capital_abonado ?? 0);
                $b_intereses_abono_capital = (float) ($abonoCapitalResult->interes_cobrado ?? 0);

                // 8. DESEMPEÑOS (tipo 4 - solo préstamo devuelto)
                $desempenosResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(con.prestamo), 0) AS prestamo_devuelto
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 4 
                      AND mo.f_cancela IS NULL 
                      AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_desempenos = (float) ($desempenosResult->prestamo_devuelto ?? 0);

                // 9. ENGANCHE CRÉDITO (tipo 19)
                $engancheCreditoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_enganche
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 19
                      AND mo.f_cancela IS NULL 
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_engancheCredito = (float) ($engancheCreditoResult->total_enganche ?? 0);

                // 10. ABONO CRÉDITO (tipos 20 y 21)
                $abonoCreditoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total_abono_credito
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento IN (20, 21)
                      AND mo.f_cancela IS NULL 
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_abonoCredito = (float) ($abonoCreditoResult->total_abono_credito ?? 0);

                // 11. CERTIFICADO DE CONFIANZA (Tabla garantias)
                $certificadoResult = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(g.monto_garantia), 0) AS total_garantia
                    FROM garantias g
                    WHERE g.cod_estatus = 1 
                      AND g.f_cancelacion IS NULL
                      AND g.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $b_certificadoConfianza = (float) ($certificadoResult->total_garantia ?? 0);

                // 12. EMPEÑOS (Nuevos préstamos - tipo 1)
                $empenosResult = DB::connection($connectionName)->selectOne("
                    SELECT
                        COUNT(DISTINCT con.contrato) as contratos,
                        COALESCE(SUM(mo.monto10), 0) as prestamo
                    FROM movimientos mo
                    INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                    WHERE mo.cod_tipo_movimiento = 1 AND mo.f_cancela IS NULL AND con.f_cancelacion IS NULL
                      AND mo.f_alta BETWEEN :fechaDel AND :fechaAl
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                // 13. VENTAS por categoría (para gráficos - opcional, puede venir de otra fuente)
                $ventasCategoriaResult = DB::connection($connectionName)->select("
                    SELECT
                        ve.cod_tipo_prenda,
                        SUM(dv.venta10) as total_venta,
                        COUNT(DISTINCT ve.cod_venta) as transacciones
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    WHERE ve.f_cancela IS NULL 
                      AND ve.f_venta BETWEEN :fechaDel AND :fechaAl
                    GROUP BY ve.cod_tipo_prenda
                ", [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                foreach ($ventasCategoriaResult as $venta) {
                    $monto = (float) $venta->total_venta;
                    switch ($venta->cod_tipo_prenda) {
                        case 1: // Alhajas
                            $b_ventasOro += $monto;
                            $b_ventasPlata += $monto * 0.3;
                            $b_ventasVarios += $monto * 0.2;
                            break;
                        case 2: // Autos
                            $b_ventasAutos += $monto;
                            break;
                        case 3: // Varios
                            $b_ventasVarios += $monto;
                            break;
                    }
                }

                // 14. INVENTARIO y CARTERA
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
                    $status = (int) $invRow->cod_estatus_prenda;
                    $cat = $invRow->CategoriaMetal;
                    $tipo = $invRow->Tipo;

                    // Poblar rd_inv
                    $loc = ($status == 9) ? 'piso' : 'depositaria';
                    if (isset($rd_inv[$loc][$cat])) {
                        $rd_inv[$loc][$cat]['monto'] += $monto;
                        $rd_inv[$loc][$cat]['cant'] += 1; // Estimado 1 prenda por registro si no hay count
                    }
                    $rd_inv[$loc]['total_monto'] += $monto;
                    $rd_inv[$loc]['total_cant'] += 1;

                    if ($status == 1) {
                        $b_carteraVigente += $monto;
                    } elseif ($status == 9) {
                        $inventarioPisoVentaTotal += $monto;

                        if ($invRow->CategoriaMetal === 'Oro') {
                            $inventarioOro += $monto;
                            $b_ventasRemate += $monto * 0.1;
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

                // 15. Balance General
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

                // ===== INGRESOS TOTALES (Fórmula corregida) =====
                $b_ingresos = $b_ventas                           // tipo 5,6
                            + $b_apartadosLiquidados              // tipo 12
                            + $b_abonoApartado                    // tipo 7,8
                            + $b_abonoCapital                     // tipo 3 (solo capital)
                            + $b_intereses_abono_capital          // tipo 3 (solo interés)
                            + $b_intereses_refrendo               // tipo 2 (interés completo)
                            + $b_intereses_desempeno              // tipo 4 (solo interés)
                            + $b_desempenos                       // tipo 4 (solo préstamo)
                            + $b_engancheCredito                  // tipo 19
                            + $b_abonoCredito                     // tipo 20,21
                            + $b_certificadoConfianza;            // garantias

                // Calcular intereses totales para otras métricas
                $b_intereses = $b_intereses_refrendo + $b_intereses_desempeno + $b_intereses_abono_capital;

                // Variables adicionales para consistencia
                $b_ventasTotales = $b_ventas + $b_apartadosLiquidados;
                $b_prestamoVentas = 0;
                $b_utilidadVenta = $b_ventasTotales;
                $b_costoVentas = 0;

                // Flujo de efectivo
                $balanceGeneral['efectivo_inicial'] += 100000;
                $balanceGeneral['efectivo_final'] = $balanceGeneral['efectivo_inicial'] + $b_ingresos - $b_egresos;
                $balanceGeneral['flujo_neto'] = $b_ingresos - $b_egresos;

                // ===== UTILIDAD NETA =====
                $b_utilidad = $b_ingresos - $b_egresos;

                // Sumar a Globales
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
                $certificadoConfianzaGlobal += $b_certificadoConfianza;

                // Acumulación detallada para Reporte de Operaciones
                $rd_empenos_contratos += (int)($empenosResult->contratos ?? 0);
                $rd_empenos_prestamo += (float)($empenosResult->prestamo ?? 0);
                $rd_ventas_total += $b_ventas;
                $rd_apartados_total += $b_apartadosLiquidados;
                $rd_abono_apartado_total += $b_abonoApartado;
                $rd_refrendos_total += $b_intereses_refrendo;
                $rd_desempenos_interes += $b_intereses_desempeno;
                $rd_abono_capital_total += $b_abonoCapital;
                $rd_abono_capital_interes += $b_intereses_abono_capital;
                $rd_creditos_total += ($b_engancheCredito + $b_abonoCredito);
                $rd_garantias_total += $b_certificadoConfianza;

                $ventasTotalesGlobal += $b_ventasTotales;
                $prestamoVentasGlobal += $b_prestamoVentas;
                $costoVentasGlobal += $b_costoVentas;

                // Meta y semáforo
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

                // Log para depuración
                \Log::info("INGRESOS SUCURSAL {$sucursal->nombre}", [
                    'ventas' => $b_ventas,
                    'apartados_liquidados' => $b_apartadosLiquidados,
                    'abono_apartado' => $b_abonoApartado,
                    'abono_capital' => $b_abonoCapital,
                    'intereses_refrendo' => $b_intereses_refrendo,
                    'intereses_desempeno' => $b_intereses_desempeno,
                    'desempenos' => $b_desempenos,
                    'enganche_credito' => $b_engancheCredito,
                    'abono_credito' => $b_abonoCredito,
                    'certificado_confianza' => $b_certificadoConfianza,
                    'TOTAL' => $b_ingresos
                ]);

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
            'tasaMora' => ($carteraVigente + $carteraVencida) > 0 ?
                round(($carteraVencida / ($carteraVigente + $carteraVencida)) * 100, 2) : 0,
            'inventarioPisoVentaTotal' => $inventarioPisoVentaTotal,
            'inventarioVarios' => $inventarioVariosTotal,
            'inventarioOro' => $inventarioOroTotal,
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
            'liquidez' => $balanceGeneral['pasivo_total'] > 0 ?
                round($balanceGeneral['activo_total'] / $balanceGeneral['pasivo_total'], 2) : 0,
            'rentabilidad' => $totalIngresos > 0 ?
                round(($utilidadNetaConsolidada / $totalIngresos) * 100, 2) : 0,
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
                'certificado_confianza' => $certificadoConfianzaGlobal,
            ],
            'prestamoVentas' => $prestamoVentasGlobal,
            'branchKPIs' => $branchKPIs,
            'chartFinanciero' => $chartFinanciero,
            'chartUtilidades' => $chartUtilidades,
            'chartInventario' => $chartInventario,
            'chartVentasCategoria' => $chartVentasCategoria,
            'chartCartera' => $chartCartera,
            'chartSucursales' => $chartSucursales,

            // Reporte Detallado consolidado para los 9 bloques y tabla de inventario
            'reporteDetallado' => [
                'bloques' => [
                    'empenos' => ['contratos' => $rd_empenos_contratos, 'prestamo' => $rd_empenos_prestamo],
                    'refrendos' => ['intereses' => $rd_refrendos_total, 'contratos' => 0, 'total_cobrado' => $rd_refrendos_total],
                    'desempenos' => ['intereses' => $rd_desempenos_interes, 'prestamo' => $desempenosGlobal, 'total_cobrado' => ($rd_desempenos_interes + $desempenosGlobal)],
                    'ventas' => ['prestamo' => 0, 'total' => $rd_ventas_total],
                    'apartados_liquidados' => ['prestamo' => 0, 'precio' => $rd_apartados_total],
                    'abono_apartado' => ['total_abonado' => $rd_abono_apartado_total],
                    'abono_capital' => ['abono' => $rd_abono_capital_total, 'interes' => $rd_abono_capital_interes],
                    'pago_credito' => ['total_cobrado' => $rd_creditos_total],
                    'garantias' => ['prendas' => 0, 'comision' => $rd_garantias_total]
                ],
                'inventario' => array_merge($rd_inv, [
                    'inv_mes_anterior' => 0,
                    'rentabilidad_pct' => 0
                ])
            ]
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
        return [
            1 => ['meta_ingresos' => 1000000, 'meta_ventas' => 800000, 'meta_utilidad' => 200000],
            2 => ['meta_ingresos' => 1500000, 'meta_ventas' => 1200000, 'meta_utilidad' => 300000],
            3 => ['meta_ingresos' => 800000, 'meta_ventas' => 600000, 'meta_utilidad' => 150000],
        ];
    }
}