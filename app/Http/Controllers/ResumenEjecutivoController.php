<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Sucursal;

class ResumenEjecutivoController extends Controller
{
    public function index(Request $request)
    {
     
        // 1. Obtencion de Fechas y Filtros (Periodo y Sucursal)
        $fechaInicio = $request->input('fecha_inicio', now()->subDays(30)->toDateString());
        // Agregamos tiempo a la fecha de fin para cubrir el día completo (como en C#: AddDays(1).AddSeconds(-1))
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';
        
        $sucursales = Sucursal::all();
        $sucursalId = $request->input('id_valora_mas');

        // 2. Extraer los datos mediante la Query que nos provee el sistema anterior
        // TODO: Adaptar el query si es necesario inyectar el @sucursalId (si es que la BD lo requiere)
        $queryMovimientos = $this->getQueryMovimientos();
        $queryInventario = $this->getQueryInventario();
        $queryGastos = $this->getQueryGastos();

        try {
            // Se ejecuta la query bruta tal como en C#
            // Notar la conversión de parámetros a arreglo de bindings. 
            // Cuidado: Dependiendo del driver (SQLSRV vs MYSQL) y PDO, parámetros por nombre pueden variar.
            // Para asegurar compatibilidad con el raw() preferimos nombrar o usar ? :
            
            // Reemplazamos los @fechaDel y @fechaAl por bindings PDO de Laravel (?)
            $queryMovimientos = str_replace(['@fechaDel', '@fechaAl'], ['?', '?'], $queryMovimientos);
            $queryGastos = str_replace(['@fechaDel', '@fechaAl'], ['?', '?'], $queryGastos);
            
            // Si tuvieramos filtro de sucursal aquí, habría que agregarlo al string o hacer IF.
            // ...

            $movimientosRaw = DB::select($queryMovimientos, [$fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $fechaInicio, $fechaFin]); // Cada bloque UNION necesita 2 bings
            // Para no enumerar 22 bindings, mejor usamos nombre con PDO:
        } catch (\Exception $e) {
            // Manejamos posibles errores sin quebrar si no hay conexión real aún
            $movimientosRaw = [];
        }

        // --- MANEJO COMPLETO DE BINDINGS ---
        // Como hay 11 select con UNION ALL, y cada uno usa @fechaDel y @fechaAl, 
        // son 22 parámetros si lo usamos posicional, por eso armaremos una query limpia.
        // Lo mejor en DB::select con nombres es mandar ['fechaDel' => $fechaInicio, 'fechaAl' => $fechaFin]
        try {
            // Nota: En PDO Laravel se usan dos puntos (:fechaDel) para bindings con nombre
            $queryMovimientosNamed = str_replace(['@fechaDel', '@fechaAl'], [':fechaDel', ':fechaAl'], $this->getQueryMovimientos());
            $movimientosRaw = DB::select($queryMovimientosNamed, [
                'fechaDel' => $fechaInicio,
                'fechaAl' => $fechaFin
            ]);
            
            $queryGastosNamed = str_replace(['@fechaDel', '@fechaAl'], [':fechaDel', ':fechaAl'], $this->getQueryGastos());
            $gastosRaw = DB::select($queryGastosNamed, [
                'fechaDel' => $fechaInicio,
                'fechaAl' => $fechaFin
            ]);

            $inventarioRaw = DB::select($this->getQueryInventario());
        } catch (\Exception $e) {
            $movimientosRaw = [];
            $gastosRaw = [];
            $inventarioRaw = [];
            // \Log::error("Error query resumen general: " . $e->getMessage());
        }

        // 3. Convertir a Colecciones (para procesar con la misma lógica LINQ de C#)
        $mov = collect($movimientosRaw);
        $inv = collect($inventarioRaw);
        
        $totalGastos = count($gastosRaw) > 0 ? (float)($gastosRaw[0]->TotalGastos ?? 0) : 0;

        // --- CÁLCULOS TIPO LINQ (Misma lógica C# pero en PHP Collections) ---
        
        $ventasData = $this->calcularVentas($mov);
        $apartadosData = $this->calcularApartadosLiquidados($mov);
        
        // Utilidades
        $utilidadVentas = $ventasData['total'] - $ventasData['prestamo'];
        $utilidadApartados = $apartadosData['total'] - $apartadosData['prestamo'];
        
        // Ingresos y Egresos
        $totalIngresos = $utilidadVentas + $utilidadApartados; // Siguiendo C#: totalIngresos = utilidadVentas + utilidadApartados
        $totalEgresos = $totalGastos;
        
        $utilidadNeta = $totalIngresos - $totalEgresos;
        
        // Inventario (Simplificado para el dash, solo sumamos lo que está en 'Piso de venta' vs 'Depositaria')
        $inventarioPisoVentaTotal = $inv->where('Ubicacion', 'Piso de venta')->sum('prestamo');
        $inventarioDepositariaTotal = $inv->where('Ubicacion', 'Depositaria')->sum('prestamo');
        
        // KPI de Empeños vigentes y Vencidos (Para la vista, aproximamos de movimientos)
        $empenosData = $this->calcularEmpenos($mov);
        $refrendosData = $this->calcularRefrendos($mov);
        
        // Datos para Gráfico 1: Ingresos vs Egresos
        $chartFinanciero = [
            'labels' => ['Ingresos Total', 'Gastos Totales', 'Utilidad Neta'],
            'data' => [$totalIngresos, $totalEgresos, $utilidadNeta]
        ];

        // Datos para Gráfico 2: Composición del Inventario (Piso vs Depositaria)
        $invOro = $inv->where('CategoriaMetal', 'Oro')->sum('prestamo');
        $invPlata = $inv->where('CategoriaMetal', 'Plata')->sum('prestamo');
        $invVarios = $inv->where('Tipo', 'Varios')->sum('prestamo');
        $invAutos = $inv->where('Tipo', 'Auto')->sum('prestamo');
        
        $chartInventario = [
            'labels' => ['Oro', 'Plata', 'Varios', 'Autos'],
            'data' => [$invOro, $invPlata, $invVarios, $invAutos]
        ];

        return view('resumen-ejecutivo.index', compact(
            'fechaInicio', 'fechaFin', 'sucursales', 'sucursalId',
            'totalIngresos', 'totalEgresos', 'utilidadNeta',
            'ventasData', 'apartadosData', 'empenosData', 'refrendosData',
            'inventarioPisoVentaTotal', 'inventarioDepositariaTotal',
            'totalGastos', 'chartFinanciero', 'chartInventario'
        ));
    }


    // ==========================================
    // MÉTODOS CÁLCULO ESTILO C# (LINQ -> Collections)
    // ==========================================

    private function calcularEmpenos($mov) {
        $empenosRows = $mov->filter(fn($r) => strtoupper(trim($r->tipo_movimiento)) === "EMPEÑO");
        return [
            'contratos' => $empenosRows->pluck('contrato')->filter()->unique()->count(),
            'prendas' => $empenosRows->sum('cantidad_prendas'),
            'prestamo' => $empenosRows->sum('prestamo') // prestamo (monto prestado)
        ];
    }

    private function calcularRefrendos($mov) {
        $refrendosRows = $mov->filter(fn($r) => strtoupper(trim($r->tipo_movimiento)) === "REFRENDO");
        return [
            'contratos' => $refrendosRows->pluck('contrato')->filter()->unique()->count(),
            'prendas' => $refrendosRows->sum('cantidad_prendas'),
            'intereses' => $refrendosRows->sum('interesCobrado'),
            'descuento' => $refrendosRows->sum('descuento'),
            'prestamo' => $refrendosRows->sum('prestamo'), // Nuevo balance
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
    // SQL STRINGS (Copiados de C# adaptados para bindings de MySQL/SQLSRV)
    // ==========================================

    private function getQueryMovimientos() {
        // Copiada literal desde tu código, se mantienen los @fechaDel y @fechaAl. 
        // Se aplicó un REPLACE arriba para transformarlos a tokens válidos por PDO :fechaDel
        return <<<SQL
       -- 1. VENTAS - METAL (ALHAJAS)
        SELECT 
            con.contrato, bolsa, cod_alhaja AS id, ve.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, al.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            (CAST(kilataje AS VARCHAR(20)) + ' K, PESO: ' + CAST(al.peso AS VARCHAR(20)) + ' G, ' + al.observaciones) AS descripcion,
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
        WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 1 AND CAST(ve.f_venta AS DATE) BETWEEN @fechaDel AND @fechaAl

        UNION ALL

        -- 2. VENTAS - VARIOS
        SELECT 
            con.contrato, bolsa, va.cod_varios AS id, ve.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, va.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            ('MARCA: ' + marca + ', MODELO: ' + modelo + ', NS:' + nserie + ', ' + va.observaciones) AS descripcion,
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
        WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 3 AND CAST(ve.f_venta AS DATE) BETWEEN @fechaDel AND @fechaAl

        UNION ALL

        -- 3. VENTAS - AUTOS
        SELECT 
            con.contrato, bolsa, au.cod_auto AS id, ve.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, au.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            ('MARCA: ' + marca + ', MODELO: ' + moa.modelo  +', COLOR:'+ color + ', AÑO: '+ CAST(au.anio AS VARCHAR(20))+ ', ' + au.observaciones) AS descripcion,
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
        WHERE ve.f_cancela IS NULL AND ve.cod_tipo_prenda = 2 AND CAST(ve.f_venta AS DATE) BETWEEN @fechaDel AND @fechaAl

        UNION ALL

        -- 4. MOVIMIENTOS (1,2,3,4) - METAL (ALHAJAS)
        SELECT 
            con.contrato, bolsa, al.cod_alhaja AS id, con.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda,
            (SELECT monto10 FROM movimientos WHERE cod_tipo_movimiento = 1 AND f_cancela IS NULL AND contrato = con.contrato) AS prestamoInicial,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior) END) AS abonoRefrendo,
            (CASE 
                WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - con.prestamo) / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)
                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior)) / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)
                ELSE 0 END) AS interesCobrado,
            (CAST(kilataje AS VARCHAR(20)) + ' K, PESO: ' + CAST(al.peso AS VARCHAR(20)) + ' G, ' + al.observaciones) AS descripcion,
            al.prestamo,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT iif(mo.monto10 < 20, (20 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)))) END) AS interes,
            0 AS descuento, NULL AS monto_garantia,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN NULL ELSE (SELECT TOP 1 periodo FROM pagos WHERE cod_contrato = mo.cod_contrato AND id = (SELECT id FROM contratos WHERE cod_contrato = mo.cod_contrato)) END) AS periodo,
            0 AS venta10, 0 AS venta,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN al.prestamo ELSE (SELECT iif(mo.monto10 < 20, (20 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)))) END) AS total,
            con.f_contrato AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM movimientos mo 
        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
        INNER JOIN prendas pre ON pre.cod_prenda = al.cod_prenda AND pre.cod_tipo_prenda = 1
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 1 AND CAST(mo.f_alta AS DATE) BETWEEN @fechaDel AND @fechaAl AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)

        UNION ALL 

        -- 5. MOVIMIENTOS (1,2,3,4) - AUTOS
        SELECT 
            con.contrato, bolsa, au.cod_auto AS id, con.cod_tipo_prenda, tm.tipo_movimiento, pre.prenda,
            (SELECT monto10 FROM movimientos WHERE cod_tipo_movimiento = 1 AND f_cancela IS NULL AND contrato = con.contrato) AS prestamoInicial,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior) END) AS abonoRefrendo,
            (CASE 
                WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - con.prestamo) / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)
                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior)) / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)
                ELSE 0 END) AS interesCobrado,
            ('MARCA: ' + ma.marca + ', MODELO: ' + CAST(au.anio AS VARCHAR(20)) + ', NS:' + au.schasis + ', ' + au.observaciones) AS descripcion,
            au.prestamo,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT iif(mo.monto10 < 20, (20 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)))) END) AS interes,
            0 AS descuento, NULL AS monto_garantia,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN NULL ELSE (SELECT TOP 1 periodo FROM pagos WHERE cod_contrato = mo.cod_contrato AND id = (SELECT id FROM contratos WHERE cod_contrato = mo.cod_contrato)) END) AS periodo,
            0 AS venta10, 0 AS venta,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN au.prestamo ELSE (SELECT iif(mo.monto10 < 20, (20 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)))) END) AS total,
            con.f_contrato AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM movimientos mo 
        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
        INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
        INNER JOIN marcas ma ON ma.cod_marca = au.cod_marca AND ma.cod_tipo_prenda = 2
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 2 AND CAST(mo.f_alta AS DATE) BETWEEN @fechaDel AND @fechaAl AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)

        UNION ALL

        -- 6. MOVIMIENTOS (1,2,3,4) - VARIOS
        SELECT 
            con.contrato, bolsa, va.cod_varios AS id, con.cod_tipo_prenda, tm.tipo_movimiento, pre.prenda,
            (SELECT monto10 FROM movimientos WHERE cod_tipo_movimiento = 1 AND f_cancela IS NULL AND contrato = con.contrato) AS prestamoInicial,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior) END) AS abonoRefrendo,
            (CASE 
                WHEN mo.cod_tipo_movimiento = 4 THEN (mo.monto10 - con.prestamo) / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)
                WHEN mo.cod_tipo_movimiento IN (2, 3) THEN (mo.monto10 - (SELECT abono FROM contratos WHERE cod_contrato = con.cod_anterior)) / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)
                ELSE 0 END) AS interesCobrado,
            ('MARCA: ' + ma.marca + ', MODELO: ' + va.modelo + ', NS:' + va.nserie + ', ' + va.observaciones) AS descripcion,
            va.prestamo,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN 0 ELSE (SELECT iif(mo.monto10 < 20, (20 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)))) END) AS interes,
            0 AS descuento, NULL AS monto_garantia,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN NULL ELSE (SELECT TOP 1 periodo FROM pagos WHERE cod_contrato = mo.cod_contrato AND id = (SELECT id FROM contratos WHERE cod_contrato = mo.cod_contrato)) END) AS periodo,
            0 AS venta10, 0 AS venta,
            (CASE WHEN mo.cod_tipo_movimiento = 1 THEN va.prestamo ELSE (SELECT iif(mo.monto10 < 20, (20 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)), (mo.monto10 / (SELECT iif(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)))) END) AS total,
            con.f_contrato AS fecha, us.nombre, 1 AS cantidad_prendas
        FROM movimientos mo 
        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
        INNER JOIN prendas pre ON pre.cod_prenda = va.cod_prenda AND pre.cod_tipo_prenda = 3
        INNER JOIN marcas ma ON ma.cod_marca = va.cod_marca AND ma.cod_tipo_prenda = 3
        INNER JOIN usuarios us ON us.cod_usuario = mo.cod_usuario
        INNER JOIN tipo_movimiento tm ON tm.cod_tipo_movimiento = mo.cod_tipo_movimiento
        WHERE mo.f_cancela IS NULL AND con.f_cancelacion IS NULL AND con.cod_tipo_prenda = 3 AND CAST(mo.f_alta AS DATE) BETWEEN @fechaDel AND @fechaAl AND mo.cod_tipo_movimiento IN (1, 2, 3, 4)

        UNION ALL

        -- 7. APARTADOS - METAL (ALHAJAS)
        SELECT 
            con.contrato, bolsa, cod_alhaja AS id, ap.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, al.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            (CAST(kilataje AS VARCHAR(20)) + ' K, PESO: ' + CAST(al.peso AS VARCHAR(20)) + ' G, ' + al.observaciones) AS descripcion,
            al.prestamo, 0 AS interes, da.descuento, NULL AS monto_garantia, NULL AS periodo,
            al.precio AS venta10, al.precio AS venta, mo.monto10 AS total, ap.f_apartado AS fecha,
            (SELECT nombre FROM [usuarios] WHERE cod_usuario = (SELECT cod_usuario FROM movimientos WHERE cod_tipo_movimiento = 7 AND contrato = da.cod_apartado)) AS usuario,
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
        WHERE apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 1 AND CAST(apg.f_pago AS DATE) BETWEEN @fechaDel AND @fechaAl

        UNION ALL

        -- 8. APARTADOS - AUTOS
        SELECT 
            con.contrato, bolsa, cod_auto AS id, ap.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, au.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            ('MARCA: ' + marca + ', MODELO: ' + moa.modelo  +', COLOR:'+ color + ', AÑO: '+ CAST(au.anio AS VARCHAR(20))+ ', ' + au.observaciones) AS descripcion,
            au.prestamo, 0 AS interes, da.descuento, NULL AS monto_garantia, NULL AS periodo,
            au.precio AS venta10, au.precio AS venta, mo.monto10 AS total, ap.f_apartado AS fecha,
            (SELECT nombre FROM [usuarios] WHERE cod_usuario = (SELECT cod_usuario FROM movimientos WHERE cod_tipo_movimiento = 7 AND contrato = da.cod_apartado)) AS usuario,
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
        WHERE apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 2 AND CAST(apg.f_pago AS DATE) BETWEEN @fechaDel AND @fechaAl

        UNION ALL

        -- 9. APARTADOS - VARIOS
        SELECT 
            con.contrato, bolsa, va.cod_varios AS id, ap.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, va.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            ('MARCA: ' + marca + ', MODELO: ' + modelo + ', NS:' + nserie + ', ' + va.observaciones) AS descripcion,
            va.prestamo, 0 AS interes, da.descuento, NULL AS monto_garantia, NULL AS periodo,
            va.precio AS venta10, va.precio AS venta, mo.monto10 AS total, ap.f_apartado AS fecha,
            (SELECT nombre FROM [usuarios] WHERE cod_usuario = (SELECT cod_usuario FROM movimientos WHERE cod_tipo_movimiento = 7 AND contrato = da.cod_apartado)) AS usuario,
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
        WHERE apg.f_cancela IS NULL AND ap.cod_tipo_prenda = 3 AND CAST(apg.f_pago AS DATE) BETWEEN @fechaDel AND @fechaAl

        UNION ALL

        -- 10. CREDITOS (19,20,21,23) - VARIOS
        SELECT 
            mo.cod_contrato AS contrato, NULL AS bolsa, op.cod_varios AS id, mo.cod_tipo_prenda, tm.tipo_movimiento,
            pre.prenda, art.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            (ma.marca + ' ' + art.modelo + ' ' + art.nserie + ' ' + art.observaciones) AS descripcion,
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
        WHERE mo.cod_estatus IN (1, 2) AND CAST(mo.f_alta AS DATE) BETWEEN @fechaDel AND @fechaAl AND mo.cod_tipo_movimiento IN (19, 20, 21, 23)

        UNION ALL

         -- 11. GARANTIAS (5, 12, 19) - VARIOS
        SELECT 
            COALESCE(con.contrato, CAST(ap.cod_apartado AS VARCHAR(50))) AS contrato, NULL AS bolsa, COALESCE(va.cod_varios, da.cod_prenda) AS id,
            gar.cod_tipo_prenda, tm.tipo_movimiento, pre.prenda, va.prestamo AS prestamoInicial, NULL AS abonoRefrendo, NULL AS interesCobrado,
            ('MARCA: ' + ma.marca + ', MODELO: ' + va.modelo + ', NS:' + va.nserie + ', ' + va.observaciones) AS descripcion,
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
        WHERE gar.f_alta BETWEEN @fechaDel AND @fechaAl 
            AND gar.f_cancelacion IS NULL AND gar.cod_tipo_movimiento IN (5, 12, 19) AND gar.cod_tipo_prenda = 3 
            AND (dv.cod_prenda IS NOT NULL OR ap.cod_apartado IS NOT NULL) AND gar.cod_estatus IN (1, 2, 4);
SQL;
    }

    private function getQueryInventario() {
        return <<<SQL
            SELECT 'Alhaja' AS Tipo, a.cod_alhaja AS Codigo, a.kilataje, a.prestamo, a.venta, a.observaciones,
                a.cod_estatus_prenda,
                CASE WHEN a.kilataje BETWEEN 500 AND 999 THEN 'Plata' WHEN a.kilataje BETWEEN 8 AND 26 THEN 'Oro' ELSE 'Varios' END AS CategoriaMetal,
                CASE WHEN a.cod_estatus_prenda = 1 THEN 'Depositaria' WHEN a.cod_estatus_prenda = 9 THEN 'Piso de venta' ELSE 'Otro' END AS Ubicacion
            FROM sistema_prendario.dbo.alhajas a WHERE a.cod_estatus_prenda IN (1,9)
            UNION ALL
            SELECT 'Varios' AS Tipo, v.cod_varios AS Codigo, NULL AS kilataje, v.prestamo, v.venta, v.observaciones,
                v.cod_estatus_prenda, 'Varios' AS CategoriaMetal,
                CASE WHEN v.cod_estatus_prenda = 1 THEN 'Depositaria' WHEN v.cod_estatus_prenda = 9 THEN 'Piso de venta' ELSE 'Otro' END AS Ubicacion
            FROM sistema_prendario.dbo.varios v WHERE v.cod_estatus_prenda IN (1,9)
            UNION ALL
            SELECT 'Auto' AS Tipo, au.cod_auto AS Codigo, NULL AS kilataje, au.prestamo, au.venta, au.observaciones,
                au.cod_estatus_prenda, 'Auto' AS CategoriaMetal,
                CASE WHEN au.cod_estatus_prenda = 1 THEN 'Depositaria' WHEN au.cod_estatus_prenda = 9 THEN 'Piso de venta' ELSE 'Otro' END AS Ubicacion
            FROM sistema_prendario.dbo.autos au WHERE au.cod_estatus_prenda IN (1,9)
SQL;
    }

    private function getQueryGastos() {
        return <<<SQL
            SELECT COALESCE(SUM(g.solicitado), 0) AS TotalGastos
            FROM sistema_prendario.dbo.gastos g
            WHERE g.activo = 1 AND g.cod_estatus = 2 AND g.f_solicitado >= @fechaDel AND g.f_solicitado <= @fechaAl
SQL;
    }
}
