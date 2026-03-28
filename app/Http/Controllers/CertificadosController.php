<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use App\Models\Sucursal;

class CertificadosController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('certificados.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
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

        $totalCertificados = 0;
        $montoCobrado = 0;
        $certificadosUtilizados = 0;
        
        // Ventas globales
        $totalVentas = 0;
        
        $dias15 = 0;
        $dias30 = 0;
        $diasOtros = 0;

        $chartCertificadosSucursal = [
            'labels' => [],
            'cantidad' => [],
            'monto' => []
        ];

        $familias = [
            '1' => 'Oro',
            '2' => 'Autos',
            '3' => 'Varios'
        ];
        
        $topFamiliasMap = [];
        $topClientesMap = [];

        foreach ($sucursalesSeleccionadas as $sucursal) {
            try {
                $dbName = 'sistema_prendario_' . $sucursal->id_valora_mas;
                $connectionName = 'certif_dynamic';

                $config = $baseConfig;
                $config['database'] = $dbName;
                Config::set("database.connections.$connectionName", $config);
                DB::purge($connectionName);

                // 1. Número de ventas en el periodo (para el % de penetración)
                $ventasTot = DB::connection($connectionName)->selectOne("
                    SELECT COUNT(*) as total
                    FROM detalle_venta dv
                    INNER JOIN ventas ve ON ve.cod_venta = dv.cod_venta
                    WHERE ve.f_cancela IS NULL AND ve.f_venta BETWEEN ? AND ?
                ", [$fechaInicio, $fechaFin]);
                
                $totalVentas += (int) $ventasTot->total;

                // 2. Certificados (Garantías)
                $garantias = DB::connection($connectionName)->select("
                    SELECT 
                        g.cod_garantia,
                        g.monto_garantia,
                        g.dias,
                        g.f_aplicacion,
                        g.cod_tipo_prenda,
                        UPPER(TRIM(CONCAT(cl.nombre, ' ', cl.a_paterno, ' ', cl.a_materno))) as nombre_cliente
                    FROM garantias g
                    INNER JOIN clientes cl ON cl.cod_cliente = g.cod_cliente
                    WHERE g.f_alta BETWEEN ? AND ? 
                      AND g.cod_estatus <> 3 
                      AND g.f_cancelacion IS NULL
                ", [$fechaInicio, $fechaFin]);

                $sucCant = 0;
                $sucMonto = 0;

                foreach ($garantias as $g) {
                    $monto = (float) $g->monto_garantia;
                    $totalCertificados++;
                    $montoCobrado += $monto;
                    
                    $sucCant++;
                    $sucMonto += $monto;

                    if (!empty($g->f_aplicacion)) {
                        $certificadosUtilizados++;
                    }

                    if ($g->dias == 15) {
                        $dias15++;
                    } elseif ($g->dias == 30) {
                        $dias30++;
                    } else {
                        $diasOtros++;
                    }

                    // Familia map
                    $famId = $g->cod_tipo_prenda ?? '3';
                    $famNombre = $familias[$famId] ?? 'Varios';
                    
                    if (!isset($topFamiliasMap[$famNombre])) {
                        $topFamiliasMap[$famNombre] = ['cantidad' => 0, 'monto' => 0];
                    }
                    $topFamiliasMap[$famNombre]['cantidad']++;
                    $topFamiliasMap[$famNombre]['monto'] += $monto;

                    // Cliente map
                    $clienteStr = $g->nombre_cliente ?: 'SIN NOMBRE';
                    if (!isset($topClientesMap[$clienteStr])) {
                        $topClientesMap[$clienteStr] = ['cantidad' => 0, 'monto' => 0];
                    }
                    $topClientesMap[$clienteStr]['cantidad']++;
                    $topClientesMap[$clienteStr]['monto'] += $monto;
                }

                $chartCertificadosSucursal['labels'][] = $sucursal->nombre;
                $chartCertificadosSucursal['cantidad'][] = $sucCant;
                $chartCertificadosSucursal['monto'][] = $sucMonto;

            } catch (\Exception $e) {
                Log::error("Error certificados {$sucursal->nombre}: " . $e->getMessage());
            }
        }

        // KPIs Finales
        $ventasConCertificadoPct = $totalVentas > 0 ? ($totalCertificados / $totalVentas) * 100 : 0;
        $certificadosNoUtilizados = $totalCertificados - $certificadosUtilizados;
        $tasaUsoPct = $totalCertificados > 0 ? ($certificadosUtilizados / $totalCertificados) * 100 : 0;
        
        $chartDistribucionPlazo = [
            'labels' => ['15 días', '30 días', 'Otros plazos'],
            'data' => [$dias15, $dias30, $diasOtros]
        ];

        // Tops
        $topFamiliasResult = [];
        foreach ($topFamiliasMap as $fam => $dataM) {
            $topFamiliasResult[] = [
                'familia' => $fam,
                'sucursal' => 'Global', 
                'cantidad' => $dataM['cantidad'],
                'monto' => $dataM['monto']
            ];
        }
        usort($topFamiliasResult, fn($a, $b) => $b['cantidad'] <=> $a['cantidad']);

        $topClientesResult = [];
        foreach ($topClientesMap as $cli => $dataM) {
            $topClientesResult[] = [
                'cliente' => $cli,
                'cantidad' => $dataM['cantidad'],
                'monto' => $dataM['monto']
            ];
        }
        usort($topClientesResult, fn($a, $b) => $b['cantidad'] <=> $a['cantidad']);
        $topClientesResult = array_slice($topClientesResult, 0, 10);

        return response()->json([
            'totalCertificados' => $totalCertificados,
            'montoCobrado' => $montoCobrado,
            'ventasConCertificadoPct' => $ventasConCertificadoPct,
            'certificadosUtilizados' => $certificadosUtilizados,
            'certificadosNoUtilizados' => $certificadosNoUtilizados,
            'tasaUsoPct' => $tasaUsoPct,
            'ingresoNeto' => $montoCobrado, // no hay costos
            'costosAsociados' => 0,
            'chartCertificadosSucursal' => $chartCertificadosSucursal,
            'chartDistribucionPlazo' => $chartDistribucionPlazo,
            'topCertificadosFamilia' => $topFamiliasResult,
            'topClientesCertificados' => $topClientesResult
        ]);
    }
}
