<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;

class MetasForecastController extends Controller
{
    public function index(Request $request)
    {
        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();
        $sucursales = Sucursal::whereNotNull('id_valora_mas')->get();

        return view('metas-forecast.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }

    public function data(Request $request)
    {
        // Estructura provisional para la UI
        
        $kpis = [
            [
                'indicador' => 'Ventas Totales',
                'real' => 0,
                'meta' => 0,
                'cumplimiento' => 0, // %
                'diferencia' => 0,
                'isManual' => false
            ],
            [
                'indicador' => 'Empeños (Monto)',
                'real' => 0,
                'meta' => 0,
                'cumplimiento' => 0,
                'diferencia' => 0,
                'isManual' => true
            ],
            [
                'indicador' => 'Intereses Cobrados',
                'real' => 0,
                'meta' => 0,
                'cumplimiento' => 0,
                'diferencia' => 0,
                'isManual' => false
            ],
            [
                'indicador' => 'Utilidad Operativa',
                'real' => 0,
                'meta' => 0,
                'cumplimiento' => 0,
                'diferencia' => 0,
                'isManual' => false
            ]
        ];

        $chartEvolucionMetaVsReal = [
            'labels' => [], // Meses
            'real' => [],
            'meta' => [],
            'anoAnterior' => []
        ];

        // Simulando datos para la gráfica de 6 meses
        $chartEvolucionMetaVsReal = [
            'labels' => ['Oct', 'Nov', 'Dic', 'Ene', 'Feb', 'Mar'],
            'real' => [120, 150, 200, 110, 130, 0], // El último mes en proceso
            'meta' => [115, 140, 190, 120, 140, 150],
            'anoAnterior' => [100, 120, 160, 105, 115, 130]
        ];

        // Cumplimiento actual general (promedio ponderado o simple para el velocímetro)
        $cumplimientoGlobal = 0; 
        
        // Tabla detallada por sucursal
        $detalleSucursales = []; // {sucursal, indicador, real, meta, cumplimiento, estatus}

        return response()->json([
            'kpis' => $kpis,
            'cumplimientoGlobal' => $cumplimientoGlobal,
            'chartEvolucionMetaVsReal' => $chartEvolucionMetaVsReal,
            'detalleSucursales' => $detalleSucursales
        ]);
    }
}
