<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sucursal;

use App\Services\PowerBIPermissionService;

class GraficasController extends Controller
{
    public function index(Request $request)
    {
        $regionales = PowerBIPermissionService::getRegionales();
        $regionalUserId = $request->input('regional_user_id');
        $idsQueFuncionan = [2, 4, 6, 8, 10, 11, 13, 15, 16, 17, 18, 19];
        $permittedIds = PowerBIPermissionService::getPermittedSucursalIds(null, $regionalUserId);
        $sucursales = Sucursal::whereNotNull('id_valora_mas')
            ->whereIn('id_valora_mas', $idsQueFuncionan)
            ->whereIn('id_valora_mas', $permittedIds)
            ->get();

        $fechaInicio = now()->startOfMonth()->toDateString();
        $fechaFin = now()->toDateString();

        return view('graficas.index', compact('fechaInicio', 'fechaFin', 'sucursales', 'regionales', 'regionalUserId'));
    }
}
