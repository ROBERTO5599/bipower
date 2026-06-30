<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GlobalDateFilterMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Si la petición incluye sucursal o fechas válidas, guardarlas en sesión
        if ($request->has('sucursal_id')) {
            session(['sucursal_id' => $request->input('sucursal_id')]);
        }
        if ($request->filled('fecha_inicio')) {
            session(['fecha_inicio' => $request->input('fecha_inicio')]);
        }
        if ($request->filled('fecha_fin')) {
            session(['fecha_fin' => $request->input('fecha_fin')]);
        }

        // 2. Si no vienen en la petición pero existen en la sesión, inyectarlas en el Request
        if (!$request->has('sucursal_id') && session()->has('sucursal_id')) {
            $request->merge(['sucursal_id' => session('sucursal_id')]);
        }
        if (!$request->has('fecha_inicio') && session()->has('fecha_inicio')) {
            $request->merge(['fecha_inicio' => session('fecha_inicio')]);
        }
        if (!$request->has('fecha_fin') && session()->has('fecha_fin')) {
            $request->merge(['fecha_fin' => session('fecha_fin')]);
        }

        return $next($request);
    }
}
