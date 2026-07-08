<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;

class GraficasController extends Controller
{
    public function index(Request $request)
    {
        $idsQueFuncionan = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19];
        $sucursales = Sucursal::whereNotNull('id_valora_mas')
            ->whereIn('id_valora_mas', $idsQueFuncionan)
            ->get();

        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();

        return view('graficas.index', compact('fechaInicio', 'fechaFin', 'sucursales'));
    }
}
