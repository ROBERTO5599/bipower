<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class InventarioPisoController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('inventario-piso.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        $fechaInicio = $request->input('fecha_inicio', now()->startOfMonth()->toDateString()) . ' 00:00:00';
        $fechaFin = $request->input('fecha_fin', now()->toDateString()) . ' 23:59:59';

        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();
        $sucursalId = $request->input('sucursal_id');

        $sucursalesSeleccionadas = $sucursalId
            ? $sucursales->where('id_valora_mas', $sucursalId)
            : $sucursales;

        $baseConfig = Config::get('database.connections.mysql');

        // ================= KPIs =================
        $valorTotalInventario = 0;
        $totalArticulosN = 0;
        $totalDias = 0;
        $ventasAcumuladas = 0;

        // Nuevos acumuladores para Flujo de Piso de Venta
        $globalInventarioInicial = 0;
        $globalDotaciones = 0;
        $globalDepositaria = 0;
        $globalDevolucion = 0;
        $globalRemate = 0;
        $globalVentas = 0;
        $globalApartado = 0;
        $globalSalidas = 0;
        $globalTraspaso = 0;
        $globalRefExt = 0;

        $valorOro = 0;
        $valorVarios = 0;
        $countOro = 0;
        $countVarios = 0;
        $perdidasMerma = 0;

        $valorPorFamilia = [];

        $rangos = [
            '0-30' => 0,
            '31-60' => 0,
            '61-90' => 0,
            '90+' => 0
        ];

        $rangosOro = $rangos;
        $rangosVarios = $rangos;

        $sucLabels = [];
        $sucValores = [];
        $sucAntiguedad = [];

        $topArticulos = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'inv_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // ================= INVENTARIO =================
                $items = DB::connection($connectionName)->select("
                    SELECT 
                        'alhajas' as tipo,
                        pre.prenda as id,
                        a.prestamo,
                        con.f_contrato as fecha,
                        CASE 
                            WHEN a.kilataje BETWEEN 8 AND 26 THEN 'Oro'
                            ELSE 'Varios'
                        END as categoria
                    FROM alhajas a
                    INNER JOIN prendas pre ON pre.cod_prenda = a.cod_prenda AND pre.cod_tipo_prenda = 1
                    LEFT JOIN contratos con ON con.cod_contrato = a.cod_contrato
                    WHERE a.cod_estatus_prenda = 9

                    UNION ALL

                    SELECT 
                        'varios',
                        pre.prenda,
                        v.prestamo,
                        con.f_contrato as fecha,
                        'Varios'
                    FROM varios v
                    INNER JOIN prendas pre ON pre.cod_prenda = v.cod_prenda AND pre.cod_tipo_prenda = 3
                    LEFT JOIN contratos con ON con.cod_contrato = v.cod_contrato
                    WHERE v.cod_estatus_prenda = 9

                    UNION ALL

                    SELECT 
                        'autos',
                        pre.prenda,
                        au.prestamo,
                        con.f_contrato as fecha,
                        'Varios'
                    FROM autos au
                    INNER JOIN prendas pre ON pre.cod_prenda = au.cod_prenda AND pre.cod_tipo_prenda = 2
                    LEFT JOIN contratos con ON con.cod_contrato = au.cod_contrato
                    WHERE au.cod_estatus_prenda = 9
                ");

                // Obtener ventas del periodo para la rotación (Costo o Venta)
                $ventasRes = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(dv.venta10), 0) as total_ventas
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    WHERE ve.f_cancela IS NULL AND ve.f_venta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);

                $ventasAcumuladas += (float) $ventasRes->total_ventas;

                $sucValor = 0;
                $sucDias = 0;
                $sucCount = 0;

                foreach ($items as $item) {
                    $dias = now()->diffInDays($item->fecha);
                    $valor = (float)$item->prestamo;

                    $valorTotalInventario += $valor;
                    $totalArticulosN++;
                    $totalDias += $dias;

                    $sucValor += $valor;
                    $sucDias += $dias;
                    $sucCount++;

                    // Categorías y Conteo
                    if ($item->categoria == 'Oro') {
                        $valorOro += $valor;
                        $countOro++;
                    } else {
                        $valorVarios += $valor;
                        $countVarios++;
                    }

                    // Familia (usamos el nombre de la prenda como familia referencial)
                    if (!isset($valorPorFamilia[$item->id])) {
                        $valorPorFamilia[$item->id] = 0;
                    }
                    $valorPorFamilia[$item->id] += $valor;

                    // Rangos
                    if ($dias <= 30) {
                        $rangos['0-30']++;
                        $item->categoria == 'Oro' ? $rangosOro['0-30']++ : $rangosVarios['0-30']++;
                    } elseif ($dias <= 60) {
                        $rangos['31-60']++;
                        $item->categoria == 'Oro' ? $rangosOro['31-60']++ : $rangosVarios['31-60']++;
                    } elseif ($dias <= 90) {
                        $rangos['61-90']++;
                        $item->categoria == 'Oro' ? $rangosOro['61-90']++ : $rangosVarios['61-90']++;
                    } else {
                        $rangos['90+']++;
                        $item->categoria == 'Oro' ? $rangosOro['90+']++ : $rangosVarios['90+']++;
                    }

                    // Top artículos
                    $topArticulos[] = [
                        'articulo' => $item->id,
                        'sucursal' => $sucursal->nombre,
                        'familia' => $item->categoria,
                        'dias' => $dias,
                        'valor' => $valor
                    ];
                }

                $sucLabels[] = $sucursal->nombre;
                $sucValores[] = $sucValor;
                $sucAntiguedad[] = $sucCount > 0 ? $sucDias / $sucCount : 0;

                // ================= PÉRDIDAS Y MERMA =================
                // Estatus comúnmente usados para bajas, siniestros o robos (ej. 11, 12, 15)
                $perdidasQuery = DB::connection($connectionName)->selectOne("
                    SELECT 
                        (SELECT COALESCE(SUM(prestamo), 0) FROM alhajas WHERE cod_estatus_prenda IN (11, 12, 15)) +
                        (SELECT COALESCE(SUM(prestamo), 0) FROM varios WHERE cod_estatus_prenda IN (11, 12, 15)) +
                        (SELECT COALESCE(SUM(prestamo), 0) FROM autos WHERE cod_estatus_prenda IN (11, 12, 15)) as total_perdidas
                ");
                $perdidasMerma += (float) $perdidasQuery->total_perdidas;

                // ================= NUEVOS CÁLCULOS DE FLUJO (PISO DE VENTA) =================
                
                // 1. Inventario Inicial: Items que ya estaban en Piso de Venta (status 9) antes de la fecha de inicio
                $invInicialQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(prestamo), 0) AS total
                    FROM (
                        SELECT prestamo FROM alhajas WHERE cod_estatus_prenda = 9 AND (p_venta IS NULL OR p_venta < :fIni1)
                        UNION ALL
                        SELECT prestamo FROM varios WHERE cod_estatus_prenda = 9 AND (p_venta IS NULL OR p_venta < :fIni2)
                        UNION ALL
                        SELECT prestamo FROM autos WHERE cod_estatus_prenda = 9 AND (p_venta IS NULL OR p_venta < :fIni3)
                    ) as t
                ", [':fIni1' => $fechaInicio, ':fIni2' => $fechaInicio, ':fIni3' => $fechaInicio]);
                $globalInventarioInicial += (float)($invInicialQ->total ?? 0);

                // 2. Dotaciones: Salidas de inventario con motivo 'DOTACION'
                $dotacionesQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(ar.prestamo), 0) AS total
                    FROM salidas_inventario sal
                    INNER JOIN detalle_salida_inventario det ON sal.cod_salida = det.cod_salida
                    INNER JOIN motivo_salida ms ON sal.cod_motivo = ms.cod_motivo
                    INNER JOIN alhajas ar ON det.cod_prenda = ar.cod_alhaja
                    WHERE ms.motivo = 'DOTACION' AND sal.f_salida BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFin]);
                $globalDotaciones += (float)($dotacionesQ->total ?? 0);

                // 3. Depositaria (entradas por vencimiento/adjudicación a status 9): Items que entraron a Piso de Venta durante el período
                $depositariaQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(prestamo), 0) AS total
                    FROM (
                        SELECT prestamo FROM alhajas WHERE cod_estatus_prenda = 9 AND p_venta BETWEEN :fIni1 AND :fFin1
                        UNION ALL
                        SELECT prestamo FROM varios WHERE cod_estatus_prenda = 9 AND p_venta BETWEEN :fIni2 AND :fFin2
                        UNION ALL
                        SELECT prestamo FROM autos WHERE cod_estatus_prenda = 9 AND p_venta BETWEEN :fIni3 AND :fFin3
                    ) as t
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFin,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFin,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFin
                ]);
                $globalDepositaria += (float)($depositariaQ->total ?? 0);

                // 4. Devolución de Crédito: Movimientos de tipo 23
                $devolucionQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(mo.monto10), 0) AS total
                    FROM movimientos mo
                    WHERE mo.cod_tipo_movimiento = 23 AND mo.f_cancela IS NULL AND mo.f_alta BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFin]);
                $globalDevolucion += (float)($devolucionQ->total ?? 0);

                // 5. Remate de Apartados Vencidos (estimado en 0 por defecto)
                $globalRemate += 0;

                // 6. Ventas (salida de piso): Ya obtenido en $ventasRes->total_ventas
                $globalVentas += (float)$ventasRes->total_ventas;

                // 7. Apartados (items que pasaron de piso a apartado status 4):
                $apartadosQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(prestamo), 0) AS total
                    FROM (
                        SELECT a.prestamo FROM alhajas a
                        INNER JOIN detalle_apartado da ON da.cod_prenda = a.cod_alhaja
                        INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado
                        WHERE ap.f_apartado BETWEEN :fIni1 AND :fFin1
                        
                        UNION ALL
                        
                        SELECT v.prestamo FROM varios v
                        INNER JOIN detalle_apartado da ON da.cod_prenda = v.cod_varios
                        INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado
                        WHERE ap.f_apartado BETWEEN :fIni2 AND :fFin2
                        
                        UNION ALL
                        
                        SELECT au.prestamo FROM autos au
                        INNER JOIN detalle_apartado da ON da.cod_prenda = au.cod_auto
                        INNER JOIN apartados ap ON ap.cod_apartado = da.cod_apartado
                        WHERE ap.f_apartado BETWEEN :fIni3 AND :fFin3
                    ) as t
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFin,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFin,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFin
                ]);
                $globalApartado += (float)($apartadosQ->total ?? 0);

                // 8. Salidas (Mermas, Bajas, Siniestros, Robos):
                $salidasQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(ar.prestamo), 0) AS total
                    FROM salidas_inventario sal
                    INNER JOIN detalle_salida_inventario det ON sal.cod_salida = det.cod_salida
                    INNER JOIN motivo_salida ms ON sal.cod_motivo = ms.cod_motivo
                    INNER JOIN alhajas ar ON det.cod_prenda = ar.cod_alhaja
                    WHERE ms.motivo IN ('FUNDICION', 'MERMA', 'SINIESTRO', 'ROBO', 'BAJA') AND sal.f_salida BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFin]);
                $globalSalidas += (float)($salidasQ->total ?? 0);

                // 9. Traspasos:
                $traspasosQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(ar.prestamo), 0) AS total
                    FROM salidas_inventario sal
                    INNER JOIN detalle_salida_inventario det ON sal.cod_salida = det.cod_salida
                    INNER JOIN motivo_salida ms ON sal.cod_motivo = ms.cod_motivo
                    INNER JOIN alhajas ar ON det.cod_prenda = ar.cod_alhaja
                    WHERE ms.motivo = 'TRASPASO' AND sal.f_salida BETWEEN :fIni AND :fFin
                ", [':fIni' => $fechaInicio, ':fFin' => $fechaFin]);
                $globalTraspaso += (float)($traspasosQ->total ?? 0);

                // 10. Refrendos Extemporáneos (renovaciones de artículos que ya estaban en piso de venta):
                $refExtQ = DB::connection($connectionName)->selectOne("
                    SELECT COALESCE(SUM(total), 0) AS total
                    FROM (
                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM alhajas WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN alhajas al ON al.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 1 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni1 AND :fFin1
                          AND al.p_venta IS NOT NULL
                          AND al.p_venta <= mo.f_alta

                        UNION ALL

                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM autos WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN autos au ON au.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 2 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni2 AND :fFin2
                          AND au.p_venta IS NOT NULL
                          AND au.p_venta <= mo.f_alta

                        UNION ALL

                        SELECT 
                            (mo.monto10 / 
                                (SELECT IF(COUNT(*)=0,1,COUNT(*)) FROM varios WHERE cod_contrato = con.cod_seguimiento)
                            ) AS total
                        FROM movimientos mo 
                        INNER JOIN contratos con ON con.cod_contrato = mo.cod_contrato
                        INNER JOIN varios va ON va.cod_contrato = con.cod_seguimiento
                        WHERE con.f_cancelacion IS NULL 
                          AND con.cod_tipo_prenda = 3 
                          AND mo.cod_tipo_movimiento = 2
                          AND mo.f_alta BETWEEN :fIni3 AND :fFin3
                          AND va.p_venta IS NOT NULL
                          AND va.p_venta <= mo.f_alta
                    ) AS t
                ", [
                    ':fIni1' => $fechaInicio, ':fFin1' => $fechaFin,
                    ':fIni2' => $fechaInicio, ':fFin2' => $fechaFin,
                    ':fIni3' => $fechaInicio, ':fFin3' => $fechaFin
                ]);
                $globalRefExt += (float)($refExtQ->total ?? 0);

            } catch (\Exception $e) {
                Log::error("Error inventario {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // ================= KPIs =================
        $antiguedadPromedio = $totalArticulosN > 0 ? $totalDias / $totalArticulosN : 0;

        $porcentajeMas30 = $totalArticulosN > 0 ? (($rangos['31-60'] + $rangos['61-90'] + $rangos['90+']) / $totalArticulosN) * 100 : 0;
        $porcentajeMas60 = $totalArticulosN > 0 ? (($rangos['61-90'] + $rangos['90+']) / $totalArticulosN) * 100 : 0;
        $porcentajeMas90 = $totalArticulosN > 0 ? ($rangos['90+'] / $totalArticulosN) * 100 : 0;

        // Ordenar top
        usort($topArticulos, fn($a, $b) => $b['dias'] <=> $a['dias']);
        $topArticulos = array_slice($topArticulos, 0, 10);

        // Rotación de Inventario (Ventas periodo / Valor Inventario actual como proxy del promedio)
        $rotacionInventario = $valorTotalInventario > 0 ? ($ventasAcumuladas / $valorTotalInventario) : 0;

        $totalIngresos = $globalInventarioInicial + $globalDotaciones + $globalDepositaria + $globalDevolucion + $globalRemate;
        $totalEgresos = $globalVentas + $globalApartado + $globalSalidas + $globalTraspaso + $globalRefExt;
        $inventarioPisoNeto = $totalIngresos - $totalEgresos;

        return response()->json([
            'valorTotalInventario' => $valorTotalInventario,
            'totalArticulosN' => $totalArticulosN,
            'antiguedadPromedioDias' => round($antiguedadPromedio, 1),
            'rotacionInventario' => round($rotacionInventario, 2),
            
            // Nuevas métricas de flujo del Piso de Venta
            'inventarioInicial' => $globalInventarioInicial,
            'dotaciones' => $globalDotaciones,
            'depositaria' => $globalDepositaria,
            'devolucion' => $globalDevolucion,
            'remate' => $globalRemate,
            'ventas' => $globalVentas,
            'apartado' => $globalApartado,
            'salidas' => $globalSalidas,
            'traspaso' => $globalTraspaso,
            'refrendoExtemporaneo' => $globalRefExt,
            'ingresosTotales' => $totalIngresos,
            'egresosTotales' => $totalEgresos,
            'inventarioPisoNeto' => $inventarioPisoNeto,

            'valorOro' => $valorOro,
            'valorVarios' => $valorVarios,
            'countOro' => $countOro,
            'countVarios' => $countVarios,
            'valorPorFamilia' => $valorPorFamilia,

            'porcentajeMas30' => round($porcentajeMas30, 1),
            'porcentajeMas60' => round($porcentajeMas60, 1),
            'porcentajeMas90' => round($porcentajeMas90, 1),

            'perdidasMerma' => $perdidasMerma,

            'chartValorAntiguedadSucursal' => [
                'labels' => $sucLabels,
                'valores' => $sucValores,
                'antiguedad' => $sucAntiguedad
            ],

            'chartDistribucionAntiguedad' => [
                'labels' => ['0-30', '31-60', '61-90', '90+'],
                'data_oro' => array_values($rangosOro),
                'data_varios' => array_values($rangosVarios)
            ],

            'topArticulosAnejos' => $topArticulos
        ]);
    }
}