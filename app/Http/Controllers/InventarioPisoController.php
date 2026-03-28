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

        return response()->json([
            'valorTotalInventario' => $valorTotalInventario,
            'totalArticulosN' => $totalArticulosN,
            'antiguedadPromedioDias' => round($antiguedadPromedio, 1),
            'rotacionInventario' => round($rotacionInventario, 2),

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