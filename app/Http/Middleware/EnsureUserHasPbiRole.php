<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPbiRole
{
    protected static $routeRoleMap = [
        'home' => 1,
        'home.movimientos-data' => 1,
        'resumen-ejecutivo.index' => 2,
        'resumen-ejecutivo.data' => 2,
        'operaciones-cartera.index' => 3,
        'operaciones-cartera.data' => 3,
        'operaciones-cartera.top-marcas' => 3,
        'ventas.index' => 4,
        'ventas.data' => 4,
        'ventas.top-marcas' => 4,
        'inventario-piso.index' => 5,
        'inventario-piso.data' => 5,
        'inventario-piso.top-marcas' => 5,
        'inventario-apartados.index' => 6,
        'inventario-apartados.data' => 6,
        'inventario-apartados.top-marcas' => 6,
        'inventario-rematado.index' => 7,
        'inventario-rematado.data' => 7,
        'clientes.index' => 8,
        'clientes.data' => 8,
        'creditos.index' => 9,
        'creditos.data' => 9,
        'inventario-credito.index' => 9,
        'inventario-credito.data' => 9,
        'inventario-credito.top-marcas' => 9,
        'certificados.index' => 10,
        'certificados.data' => 10,
        'gastos-finanzas.index' => 11,
        'gastos-finanzas.data' => 11,
        'bancos.index' => 12,
        'bancos.data' => 12,
        'reporte-pagos-tarjeta.index' => 13,
        'reporte-pagos-tarjeta.data' => 13,
        'control-caja-arqueos.index' => 14,
        'control-caja-arqueos.data' => 14,
        'colaboradores.index' => 15,
        'colaboradores.data' => 15,
        'bonos-comisiones.index' => 16,
        'bonos-comisiones.data' => 16,
        'metas-forecast.index' => 17,
        'metas-forecast.data' => 17,
        'tablero-control.index' => 18,
        'tablero-control.data' => 18,
        'graficas.index' => 19,
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            if (is_null($user->getPbiRoleType())) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'nick_name' => 'No cuentas con permisos, dirígete a Presidencia.',
                ]);
            }

            // Validar acceso al módulo específico si la ruta está mapeada a un rol
            $routeName = $request->route() ? $request->route()->getName() : null;
            if ($routeName && isset(self::$routeRoleMap[$routeName])) {
                $requiredRoleId = self::$routeRoleMap[$routeName];
                if (!$user->hasPowerBiRole($requiredRoleId)) {
                    return redirect($user->getFirstAuthorizedPowerBiRoute());
                }
            }
        }

        return $next($request);
    }
}