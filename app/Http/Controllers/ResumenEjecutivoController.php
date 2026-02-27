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

    // Endpoint AJAX para obtener los datos
    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString());
        $fechaFin = $request->input('fecha_fin', now()->toDateString());
        $fechaFinQuery = $fechaFin . ' 23:59:59';

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        $sucursalId = $request->input('sucursal_id');

        if ($sucursalId) {
            $sucursalesSeleccionadas = $sucursales->where('id_valora_mas', $sucursalId);
        } else {
            $sucursalesSeleccionadas = $sucursales;
        }

        $baseConfig = Config::get('database.connections.mysql');

        $globalMovimientos = collect();
        $globalInventario = collect();
        $totalGastosGlobal = 0;

        $branchKPIs = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
            $connectionName = 'dynamic_kpi';

            try {
                if ($baseConfig) {
                    $config = $baseConfig;
                    $config['database'] = $dbName;
                    Config::set("database.connections.{$connectionName}", $config);
                    DB::purge($connectionName);
                } else {
                    throw new \Exception("Base MySQL configuration not found.");
                }

                $queryMov = $this->getQueryMovimientos();
                $movimientosRaw = DB::connection($connectionName)->select($queryMov, [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $queryGas = $this->getQueryGastos();
                $gastosRaw = DB::connection($connectionName)->select($queryGas, [':fechaDel' => $fechaInicio, ':fechaAl' => $fechaFinQuery]);

                $queryInv = $this->getQueryInventario();
                $inventarioRaw = DB::connection($connectionName)->select($queryInv, []);

                $mov = collect($movimientosRaw);
                $inv = collect($inventarioRaw);
                $gastos = count($gastosRaw) > 0 ? (float)($gastosRaw[0]->TotalGastos ?? 0) : 0;

                $mov->each(function($item) use ($sucursal) { $item->sucursal = $sucursal->nombre; });
                $inv->each(function($item) use ($sucursal) { $item->sucursal = $sucursal->nombre; });

                $globalMovimientos = $globalMovimientos->merge($mov);
                $globalInventario = $globalInventario->merge($inv);
                $totalGastosGlobal += $gastos;

                $branchKPIs[$sucursal->nombre] = $this->calculateBranchKPIs($mov, $inv, $gastos);

            } catch (\Exception $e) {
                Log::error("Error procesando sucursal {$sucursal->nombre} ({$dbName}): " . $e->getMessage());
                continue;
            }
        }

        $ventasData = $this->calcularVentas($globalMovimientos);
        $apartadosData = $this->calcularApartadosLiquidados($globalMovimientos);
        
        $utilidadVentas = $ventasData['total'] - $ventasData['prestamo'];
        $utilidadApartados = $apartadosData['total'] - $apartadosData['prestamo'];
        
        $totalIngresos = $utilidadVentas + $utilidadApartados;
        $totalEgresos = $totalGastosGlobal;
        $utilidadNeta = $totalIngresos - $totalEgresos;
        
        $inventarioPisoVentaTotal = $globalInventario->where('Ubicacion', 'Piso de venta')->sum('prestamo');
        
        $empenosData = $this->calcularEmpenos($globalMovimientos);
        
        $chartFinanciero = [
            'labels' => ['Ingresos', 'Gastos', 'Utilidad'],
            'data' => [$totalIngresos, $totalEgresos, $utilidadNeta]
        ];

        $invOro = $globalInventario->where('CategoriaMetal', 'Oro')->sum('prestamo');
        $invPlata = $globalInventario->where('CategoriaMetal', 'Plata')->sum('prestamo');
        $invVarios = $globalInventario->where('Tipo', 'Varios')->sum('prestamo');
        $invAutos = $globalInventario->where('Tipo', 'Auto')->sum('prestamo');
        
        $chartInventario = [
            'labels' => ['Oro', 'Plata', 'Varios', 'Autos'],
            'data' => [$invOro, $invPlata, $invVarios, $invAutos]
        ];

        $chartSucursales = $this->prepareBranchChartData($branchKPIs);

        return response()->json([
            'totalIngresos' => $totalIngresos,
            'totalEgresos' => $totalEgresos,
            'utilidadNeta' => $utilidadNeta,
            'inventarioPisoVentaTotal' => $inventarioPisoVentaTotal,
            'empenosData' => $empenosData,
            'chartFinanciero' => $chartFinanciero,
            'chartInventario' => $chartInventario,
            'branchKPIs' => $branchKPIs,
            'chartSucursales' => $chartSucursales
        ]);
    }

    // --- CÁLCULOS POR SUCURSAL ---

    private function calculateBranchKPIs($mov, $inv, $gastos)
    {
        $ventas = $this->calcularVentas($mov);
        $apartados = $this->calcularApartadosLiquidados($mov);

        $utilidad = ($ventas['total'] - $ventas['prestamo']) + ($apartados['total'] - $apartados['prestamo']);
        $ingresos = $utilidad;
        $utilidadNeta = $ingresos - $gastos;

        $ventasTotalesMonto = $ventas['total'] + $apartados['total'];
        $margenBruto = $ventasTotalesMonto > 0 ? ($utilidad / $ventasTotalesMonto) * 100 : 0;

        return [
            'ingresos' => $ingresos,
            'gastos' => $gastos,
            'utilidad_neta' => $utilidadNeta,
            'margen_bruto_pct' => $margenBruto,
            'inventario_total' => $inv->sum('prestamo'),
        ];
    }

    private function prepareBranchChartData($branchKPIs)
    {
        $labels = array_keys($branchKPIs);
        $ingresos = [];
        $utilidades = [];

        foreach ($branchKPIs as $kpi) {
            $ingresos[] = $kpi['ingresos'];
            $utilidades[] = $kpi['utilidad_neta'];
        }

        return [
            'labels' => $labels,
            'ingresos' => $ingresos,
            'utilidades' => $utilidades
        ];
    }

    // --- MÉTODOS DE CÁLCULO (LINQ-like) ---

    private function calcularEmpenos($mov) {
        $empenosRows = $mov->filter(fn($r) => strtoupper(trim($r->tipo_movimiento)) === "EMPEÑO");
        return [
            'contratos' => $empenosRows->pluck('contrato')->filter()->unique()->count(),
            'prendas' => $empenosRows->sum('cantidad_prendas'),
            'prestamo' => $empenosRows->sum('prestamo')
        ];
    }

    private function calcularRefrendos($mov) {
        $refrendosRows = $mov->filter(fn($r) => strtoupper(trim($r->tipo_movimiento)) === "REFRENDO");
        return [
            'contratos' => $refrendosRows->pluck('contrato')->filter()->unique()->count(),
            'prendas' => $refrendosRows->sum('cantidad_prendas'),
            'intereses' => $refrendosRows->sum('interesCobrado'),
            'descuento' => $refrendosRows->sum('descuento'),
            'prestamo' => $refrendosRows->sum('prestamo'),
            'totalCobrado' => $refrendosRows->sum('total')
        ];
    }
    
    private function calcularVentas($mov) {
        $ventasRows = $mov->filter(function($r) {
            $tm = strtoupper(trim($r->tipo_movimiento));
            $garantia = isset($r->monto_garantia) ? (float)$r->monto_garantia : 0;
            return ($tm === "VENTA MENUEDEO" || $tm === "VENTA MAYOREO") && $garantia == 0;
        });

        return [
            'prendas' => $ventasRows->count(),
            'prestamo' => $ventasRows->sum('prestamo'),
            'descuentos' => $ventasRows->sum('descuento'),
            'total' => $ventasRows->sum('total'),
        ];
    }
    
    private function calcularApartadosLiquidados($mov) {
        $apartadosRows = $mov->filter(function($r) {
            $tm = strtoupper(trim($r->tipo_movimiento));
            $garantia = isset($r->monto_garantia) ? (float)$r->monto_garantia : 0;
            return ($tm === "LIQUIDACION DE APARTADO") && $garantia == 0;
        });

        return [
            'prendas' => $apartadosRows->count(),
            'prestamo' => $apartadosRows->sum('prestamoInicial'),
            'precio' => $apartadosRows->sum('total'),
            'total' => $apartadosRows->sum('venta'),
        ];
    }

    // ==========================================
    // SQL STRINGS (CONVERTIDOS A MYSQL)
    // ==========================================

    private function getQueryMovimientos() {
        return <<<SQL
       -- 1. VENTAS - METAL (ALHAJAS)
        SELECT 
            con.contrato, NULL as bolsa, al.cod_alhaja AS id, ve.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, al.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT(CAST(kilataje AS CHAR), ' K, PESO: ', CAST(al.peso AS CHAR), ' G, ', al.observaciones) AS descripcion,
            al.prestamo, 0 AS interes, dv.descuento, NULL AS monto_garantia, NULL AS periodo,
            dv.venta10, dv.venta, dv.venta10 AS total, ve.f_venta AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM detalle_venta dv 
        INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
        INNER JOIN alhajas al ON al.cod_alhaja = dv.cod_prenda
        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda
        LEFT JOIN contratos con ON con.cod_contrato = al.cod_contrato
        INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento 
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        inner join tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 1 AND CAST(ve.f_venta AS DATE) BETWEEN :fechaDel AND :fechaAl

        UNION ALL

        -- 2. VENTAS - VARIOS
        SELECT 
            con.contrato, NULL as bolsa, va.cod_varios AS id, ve.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, va.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT('MARCA: ', marca, ', MODELO: ', modelo, ', NS:', nserie, ', ', va.observaciones) AS descripcion,
            va.prestamo, 0 AS interes, dv.descuento, NULL AS monto_garantia, NULL AS periodo,
            dv.venta10, dv.venta, dv.venta10 AS total, ve.f_venta AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM detalle_venta dv 
        INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
        INNER JOIN varios va ON va.cod_varios = dv.cod_prenda
        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda
        INNER JOIN marcas ma ON ma.cod_marca = va.cod_marca
        LEFT JOIN contratos con ON con.cod_contrato = va.cod_contrato 
        INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento 
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        inner join tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 3 AND CAST(ve.f_venta AS DATE) BETWEEN :fechaDel AND :fechaAl

        UNION ALL

        -- 3. VENTAS - AUTOS
        SELECT 
            con.contrato, NULL as bolsa, au.cod_auto AS id, ve.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, au.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT('MARCA: ', marca, ', MODELO: ', moa.modelo, ', COLOR:', color, ', AÑO: ', CAST(au.anio AS CHAR), ', ', au.observaciones) AS descripcion,
            au.prestamo, 0 AS interes, dv.descuento, NULL AS monto_garantia, NULL AS periodo,
            dv.venta10, dv.venta, dv.venta10 AS total, ve.f_venta AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM detalle_venta dv 
        INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
        INNER JOIN autos au ON au.cod_auto = dv.cod_prenda
        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda
        INNER JOIN marcas ma ON ma.cod_marca = au.cod_marca
        LEFT JOIN contratos con ON con.cod_contrato = au.cod_contrato 
        INNER JOIN movimientos mo ON mo.cod_movimiento = ve.cod_movimiento 
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        inner join tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        INNER JOIN modelo_autos moa ON moa.cod_modelo = au.cod_modelo  
        WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 2 AND CAST(ve.f_venta AS DATE) BETWEEN :fechaDel AND :fechaAl

        UNION ALL

        -- 4. MOVIMIENTOS (1,2,3,4) - METAL (ALHAJAS)
        SELECT 
            con.contrato, NULL as bolsa, al.cod_alhaja AS id, con.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda,
            (SELECT monto10 FROM movimientos WHERE cod_tipo_movimiento = 1 AND f_cancela IS NULL AND contrato = con.contrato LIMIT 1) AS prestamoInicial,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior LIMIT 1) END) AS abonoRefrendo,
            (CASE 
                WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - con.prestamo) / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)
                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior LIMIT 1)) / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)
                ELSE 0 END) AS interesCobrado,
            CONCAT(CAST(kilataje AS CHAR), ' K, PESO: ', CAST(al.peso AS CHAR), ' G, ', al.observaciones) AS descripcion,
            al.prestamo,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT IF(mo.monto10 < 20, (20 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)))) END) AS interes,
            0 AS descuento, NULL AS monto_garantia,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN NULL ELSE (SELECT periodo FROM pagos WHERE cod_contrato = mo.cod_contrato AND id = (SELECT id FROM contratos WHERE cod_contrato = mo.cod_contrato LIMIT 1) LIMIT 1) END) AS periodo,
            0 AS venta10, 0 AS venta,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN al.prestamo ELSE (SELECT IF(mo.monto10 < 20, (20 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)))) END) AS total,
            con.f_contrato AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM movimientos mo 
        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 1 AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)

        UNION ALL 

        -- 5. MOVIMIENTOS (1,2,3,4) - AUTOS
        SELECT 
            con.contrato, NULL as bolsa, au.cod_auto AS id, con.cod_tipo_prenda, tm.tipo_movimiento, pre.prenda,
            (SELECT monto10 FROM movimientos WHERE cod_tipo_movimiento = 1 AND f_cancela IS NULL AND contrato = con.contrato LIMIT 1) AS prestamoInicial,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior LIMIT 1) END) AS abonoRefrendo,
            (CASE 
                WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - con.prestamo) / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)
                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior LIMIT 1)) / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)
                ELSE 0 END) AS interesCobrado,
            CONCAT('MARCA: ', ma.marca, ', MODELO: ', CAST(au.anio AS CHAR), ', NS:', au.schasis, ', ', au.observaciones) AS descripcion,
            au.prestamo,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT IF(mo.monto10 < 20, (20 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)))) END) AS interes,
            0 AS descuento, NULL AS monto_garantia,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN NULL ELSE (SELECT periodo FROM pagos WHERE cod_contrato = mo.cod_contrato AND id = (SELECT id FROM contratos WHERE cod_contrato = mo.cod_contrato LIMIT 1) LIMIT 1) END) AS periodo,
            0 AS venta10, 0 AS venta,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN au.prestamo ELSE (SELECT IF(mo.monto10 < 20, (20 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)))) END) AS total,
            con.f_contrato AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM movimientos mo 
        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
        INNER JOIN marcas ma ON ma.cod_marca = au.cod_marca AND ma.cod_tipo_prenda = 2
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 2 AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)

        UNION ALL

        -- 6. MOVIMIENTOS (1,2,3,4) - VARIOS
        SELECT 
            con.contrato, NULL as bolsa, va.cod_varios AS id, con.cod_tipo_prenda, tm.tipo_movimiento, pre.prenda,
            (SELECT monto10 FROM movimientos WHERE cod_tipo_movimiento = 1 AND f_cancela IS NULL AND contrato = con.contrato LIMIT 1) AS prestamoInicial,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior LIMIT 1) END) AS abonoRefrendo,
            (CASE 
                WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - con.prestamo) / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)
                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior LIMIT 1)) / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)
                ELSE 0 END) AS interesCobrado,
            CONCAT('MARCA: ', ma.marca, ', MODELO: ', va.modelo, ', NS:', va.nserie, ', ', va.observaciones) AS descripcion,
            va.prestamo,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT IF(mo.monto10 < 20, (20 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)))) END) AS interes,
            0 AS descuento, NULL AS monto_garantia,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN NULL ELSE (SELECT periodo FROM pagos WHERE cod_contrato = mo.cod_contrato AND id = (SELECT id FROM contratos WHERE cod_contrato = mo.cod_contrato LIMIT 1) LIMIT 1) END) AS periodo,
            0 AS venta10, 0 AS venta,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN va.prestamo ELSE (SELECT IF(mo.monto10 < 20, (20 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)))) END) AS total,
            con.f_contrato AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM movimientos mo 
        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
        INNER JOIN marcas ma ON ma.cod_marca = va.cod_marca AND ma.cod_tipo_prenda = 3
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 3 AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)

        UNION ALL

        -- 7. APARTADOS - METAL (ALHAJAS)
        SELECT 
            con.contrato, NULL as bolsa, cod_alhaja AS id, ap.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, al.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT(CAST(kilataje AS CHAR), ' K, PESO: ', CAST(al.peso AS CHAR), ' G, ', al.observaciones) AS descripcion,
            al.prestamo, 0 AS interes, da.descuento, NULL AS monto_garantia, NULL AS periodo,
            al.precio AS venta10, al.precio AS venta, mo.monto10 AS total, ap.f_apartado AS fecha,
            (SELECT nombre FROM usuarios WHERE cod_usuario = (SELECT cod_usuario FROM movimientos WHERE cod_tipo_movimiento = 7 AND contrato = da.cod_apartado LIMIT 1) LIMIT 1) AS usuario,
            1 AS cantidad_prendas
        FROM apartado_pagos apg 
        INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
        INNER JOIN alhajas al ON al.cod_alhaja = da.cod_prenda
        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda
        LEFT JOIN contratos con ON con.cod_contrato = al.cod_contrato
        INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento 
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        inner join tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 1 AND CAST(apg.f_pago AS DATE) BETWEEN :fechaDel AND :fechaAl

        UNION ALL

        -- 8. APARTADOS - AUTOS
        SELECT 
            con.contrato, NULL as bolsa, cod_auto AS id, ap.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, au.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT('MARCA: ', marca, ', MODELO: ', moa.modelo, ', COLOR:', color, ', AÑO: ', CAST(au.anio AS CHAR), ', ', au.observaciones) AS descripcion,
            au.prestamo, 0 AS interes, da.descuento, NULL AS monto_garantia, NULL AS periodo,
            au.precio AS venta10, au.precio AS venta, mo.monto10 AS total, ap.f_apartado AS fecha,
            (SELECT nombre FROM usuarios WHERE cod_usuario = (SELECT cod_usuario FROM movimientos WHERE cod_tipo_movimiento = 7 AND contrato = da.cod_apartado LIMIT 1) LIMIT 1) AS usuario,
            1 AS cantidad_prendas
        FROM apartado_pagos apg 
        INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado 
        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
        INNER JOIN autos au ON au.cod_auto = da.cod_prenda
        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda
        LEFT JOIN contratos con ON con.cod_contrato = au.cod_contrato
        INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento 
        inner join tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        INNER JOIN marcas ma ON ma.cod_marca = au.cod_marca
        INNER JOIN modelo_autos moa ON moa.cod_modelo = au.cod_modelo  
        WHERE apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 2 AND CAST(apg.f_pago AS DATE) BETWEEN :fechaDel AND :fechaAl

        UNION ALL

        -- 9. APARTADOS - VARIOS
        SELECT 
            con.contrato, NULL as bolsa, va.cod_varios AS id, ap.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, va.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT('MARCA: ', marca, ', MODELO: ', modelo, ', NS:', nserie, ', ', va.observaciones) AS descripcion,
            va.prestamo, 0 AS interes, da.descuento, NULL AS monto_garantia, NULL AS periodo,
            va.precio AS venta10, va.precio AS venta, mo.monto10 AS total, ap.f_apartado AS fecha,
            (SELECT nombre FROM usuarios WHERE cod_usuario = (SELECT cod_usuario FROM movimientos WHERE cod_tipo_movimiento = 7 AND contrato = da.cod_apartado LIMIT 1) LIMIT 1) AS usuario,
            1 AS cantidad_prendas
        FROM apartado_pagos apg 
        INNER JOIN apartados ap ON ap.cod_apartado = apg.cod_apartado
        INNER JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado
        INNER JOIN varios va ON va.cod_varios = da.cod_prenda
        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda
        INNER JOIN marcas ma ON ma.cod_marca = va.cod_marca
        LEFT JOIN contratos con ON con.cod_contrato = va.cod_contrato 
        INNER JOIN movimientos mo ON mo.cod_movimiento = apg.cod_movimiento
        inner join tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        WHERE apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 3 AND CAST(apg.f_pago AS DATE) BETWEEN :fechaDel AND :fechaAl

        UNION ALL

        -- 10. CREDITOS (19,20,21,23) - VARIOS
        SELECT 
            mo.cod_contrato AS contrato, NULL AS bolsa, op.cod_varios AS id, mo.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, art.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT(ma.marca, ' ', art.modelo, ' ', art.nserie, ' ', art.observaciones) AS descripcion,
            art.prestamo, 0 AS interes, 0 AS descuento, NULL AS monto_garantia, NULL AS periodo,
            op.monto_total AS venta10, op.monto_total AS venta, mo.monto AS total, CAST(mo.f_alta AS DATE) AS fecha,
            us.nombre, 1 AS cantidad_prendas
        FROM movimientos mo 
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        inner join tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        INNER JOIN creditos op ON op.cod_credito = mo.cod_contrato
        INNER JOIN varios art ON art.cod_varios = op.cod_varios
        INNER JOIN prendas pre ON pre.cod_prenda = art.cod_prenda 
        INNER JOIN marcas ma ON ma.cod_marca = art.cod_marca
        WHERE mo.cod_estatus IN (1, 2) AND CAST(mo.f_alta AS DATE) BETWEEN :fechaDel AND :fechaAl AND mo.cod_tipo_movimiento IN (19, 20, 21, 23)

        UNION ALL

         -- 11. GARANTIAS (5, 12, 19) - VARIOS
        SELECT 
            COALESCE(con.contrato, CAST(ap.cod_apartado AS CHAR)) AS contrato, NULL AS bolsa, COALESCE(va.cod_varios, da.cod_prenda) AS id,
            gar.cod_tipo_prenda, tm.tipo_movimiento, pre.prenda, va.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            CONCAT('MARCA: ', ma.marca, ', MODELO: ', va.modelo, ', NS:', va.nserie, ', ', va.observaciones) AS descripcion,
            va.prestamo, 0 AS interes, COALESCE(dv.descuento, da.descuento, 0) AS descuento, gar.monto_garantia, NULL AS periodo,
            COALESCE(dv.venta10, va.precio) AS venta10, COALESCE(dv.venta, va.precio) AS venta, COALESCE(dv.venta10, gar.monto_garantia) AS total,
            CAST(gar.f_alta AS DATE) AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM garantias gar 
        INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = gar.cod_tipo_movimiento
        INNER JOIN usuarios us ON us.cod_usuario = gar.cod_usuario
        INNER JOIN varios va ON va.cod_varios = gar.id_prenda
        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda
        INNER JOIN marcas ma ON ma.cod_marca = va.cod_marca
        LEFT JOIN detalle_venta dv ON dv.cod_prenda = gar.cod_movimiento
        LEFT JOIN apartados ap ON ap.cod_apartado = gar.cod_garantia
        LEFT JOIN detalle_apartado da ON da.cod_apartado = ap.cod_apartado AND da.cod_prenda = va.cod_varios
        LEFT JOIN contratos con ON con.cod_contrato = va.cod_contrato
        WHERE gar.f_alta BETWEEN :fechaDel AND :fechaAl
            AND gar.f_cancelacion IS NULL AND gar.cod_tipo_movimiento IN (5, 12, 19) AND gar.cod_tipo_prenda = 3 
            AND (dv.cod_prenda IS NOT NULL OR ap.cod_apartado IS NOT NULL) AND gar.cod_estatus IN (1, 2, 4);
SQL;
    }

    private function getQueryInventario() {
        // En MySQL las tablas deben referenciarse sin el prefijo de base de datos 'sistema_prendario.dbo.'
        // Asumimos que la conexión ya está en la BD correcta, por lo que quitamos el prefijo.
        // También quitamos 'dbo.'
        return <<<SQL
            SELECT 'Alhaja' AS Tipo, a.cod_alhaja AS Codigo, a.kilataje, a.prestamo, a.venta, a.observaciones,
                a.cod_estatus_prenda,
                CASE WHEN a.kilataje BETWEEN 500 AND 999 THEN 'Plata' WHEN a.kilataje BETWEEN 8 AND 26 THEN 'Oro' ELSE 'Varios' END AS CategoriaMetal,
                CASE WHEN a.cod_estatus_prenda = 1 THEN 'Depositaria' WHEN a.cod_estatus_prenda = 9 THEN 'Piso de venta' ELSE 'Otro' END AS Ubicacion
            FROM alhajas a WHERE a.cod_estatus_prenda IN (1,9)
            UNION ALL
            SELECT 'Varios' AS Tipo, v.cod_varios AS Codigo, NULL AS kilataje, v.prestamo, v.venta, v.observaciones,
                v.cod_estatus_prenda, 'Varios' AS CategoriaMetal,
                CASE WHEN v.cod_estatus_prenda = 1 THEN 'Depositaria' WHEN v.cod_estatus_prenda = 9 THEN 'Piso de venta' ELSE 'Otro' END AS Ubicacion
            FROM varios v WHERE v.cod_estatus_prenda IN (1,9)
            UNION ALL
            SELECT 'Auto' AS Tipo, au.cod_auto AS Codigo, NULL AS kilataje, au.prestamo, au.venta, au.observaciones,
                au.cod_estatus_prenda, 'Auto' AS CategoriaMetal,
                CASE WHEN au.cod_estatus_prenda = 1 THEN 'Depositaria' WHEN au.cod_estatus_prenda = 9 THEN 'Piso de venta' ELSE 'Otro' END AS Ubicacion
            FROM autos au WHERE au.cod_estatus_prenda IN (1,9)
SQL;
    }

    private function getQueryGastos() {
        return <<<SQL
            SELECT COALESCE(SUM(g.solicitado), 0) AS TotalGastos
            FROM gastos g
            WHERE g.activo = 1 AND g.cod_estatus = 2 AND g.f_solicitado >= :fechaDel AND g.f_solicitado <= :fechaAl
SQL;
    }
}
