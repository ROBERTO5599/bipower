<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'nombre',
        'primer_apellido',
        'segundo_apellido',
        'nick_name',
        'password',
        'sucursal_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Esto le dice a Laravel que el "username" es nick_name
    public function getAuthIdentifierName()
    {
        return 'nick_name';
    }

    // Nombre completo accesible como $user->nombre_completo
    public function getNombreCompletoAttribute()
    {
        return "{$this->nombre} {$this->primer_apellido} {$this->segundo_apellido}";
    }

    public function username()
    {
        return 'nick_name';
    }

    // Relación con sucursal
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Roles asignados al usuario en la intranet (tabla user_role).
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
    }

    /**
     * Roles de módulos de Power BI asignados al usuario (tabla roles_user_powerbi).
     */
    public function rolesPowerBi()
    {
        return $this->belongsToMany(RolePowerBi::class, 'roles_user_powerbi', 'user_id', 'role_powerbi_id')
                    ->withTimestamps();
    }

    /**
     * Verifica si el usuario tiene permiso para un módulo de Power BI específico.
     * Los Super Administradores (rol 77 o PBI Super Administrador) tienen acceso total.
     */
    public function hasPowerBiRole($roleId)
    {
        $userRoles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();
        $roleNames = $userRoles->pluck('nombre')->toArray();

        // 1. Super Administrador (77 o is_admin): Acceso total
        if (
            $this->is_admin ||
            in_array('PBI Super Administrador', $roleNames) || 
            in_array('Super Administrador', $roleNames) || 
            $userRoles->contains('id', 77)
        ) {
            return true;
        }

        // 2. Inicio (Rol 1): Acceso automático para roles normales (Gerente 79, Regional 78, etc.) o con módulos
        if ((int)$roleId === 1 && !is_null($this->getPbiRoleType())) {
            return true;
        }

        // 3. Módulos adicionales (2 al 19): Se consultan desde los nuevos roles Power BI (roles_user_powerbi)
        if ($this->relationLoaded('rolesPowerBi')) {
            return $this->rolesPowerBi->contains('id', $roleId);
        }

        return $this->rolesPowerBi()->where('roles_powerbi.id', $roleId)->exists();
    }

    /**
     * Retorna la URL de la primera pantalla autorizada para el usuario.
     */
    public function getFirstAuthorizedPowerBiRoute(): string
    {
        if ($this->hasPowerBiRole(1)) {
            return route('home');
        }

        $map = [
            1 => 'home',
            2 => 'resumen-ejecutivo.index',
            3 => 'operaciones-cartera.index',
            4 => 'ventas.index',
            5 => 'inventario-piso.index',
            6 => 'inventario-apartados.index',
            7 => 'inventario-rematado.index',
            8 => 'clientes.index',
            9 => 'creditos.index',
            10 => 'certificados.index',
            11 => 'gastos-finanzas.index',
            12 => 'bancos.index',
            13 => 'reporte-pagos-tarjeta.index',
            14 => 'control-caja-arqueos.index',
            15 => 'colaboradores.index',
            16 => 'bonos-comisiones.index',
            17 => 'metas-forecast.index',
            18 => 'tablero-control.index',
            19 => 'graficas.index',
        ];

        $userRoleIds = $this->rolesPowerBi()->pluck('roles_powerbi.id')->toArray();
        foreach ($map as $roleId => $routeName) {
            if (in_array($roleId, $userRoleIds)) {
                return route($routeName);
            }
        }

        return route('home');
    }

    /**
     * Sucursales asignadas a este usuario como Gerente Regional.
     */
    public function sucursalesRegionales()
    {
        return $this->hasMany(Sucursal::class, 'regional_user_id');
    }

    /**
     * Verificar si el usuario tiene un rol específico.
     */
    public function hasRole($roleName)
    {
        return $this->roles->pluck('nombre')->contains($roleName);
    }

    /**
     * Determinar el tipo de rol de Power BI para el usuario.
     * @return string|null 'super_admin' | 'regional' | 'gerente' | 'modulo_user' | null
     */
    public function getPbiRoleType()
    {
        $userRoles = $this->relationLoaded('roles') ? $this->roles : $this->roles()->get();
        $roleNames = $userRoles->pluck('nombre')->toArray();

        if (
            $this->is_admin ||
            in_array('PBI Super Administrador', $roleNames) || 
            in_array('Super Administrador', $roleNames) || 
            $userRoles->contains('id', 77)
        ) {
            return 'super_admin';
        }

        if (
            in_array('PBI Regional', $roleNames) || 
            in_array('Regional', $roleNames) || 
            $userRoles->contains('id', 78)
        ) {
            return 'regional';
        }

        if (
            in_array('PBI Gerente', $roleNames) || 
            in_array('Gerente', $roleNames) || 
            $userRoles->contains('id', 79)
        ) {
            return 'gerente';
        }

        // Si tiene asignado algún rol en la tabla roles_user_powerbi
        $hasPbiRoles = $this->relationLoaded('rolesPowerBi') 
            ? $this->rolesPowerBi->isNotEmpty() 
            : $this->rolesPowerBi()->exists();

        if ($hasPbiRoles) {
            return 'modulo_user';
        }

        // Si no se le ha asignado ningún rol de Power BI, retorna null (sin acceso)
        return null;
    }

    /**
     * Obtener los id_valora_mas de las sucursales permitidas para Power BI.
     * @param int|string|null $regionalUserIdFilter
     * @return array
     */
    public function getPbiPermittedSucursalIds($regionalUserIdFilter = null)
    {
        $roleType = $this->getPbiRoleType();

        if ($roleType === 'super_admin') {
            $query = Sucursal::whereNotNull('id_valora_mas');
            if (!empty($regionalUserIdFilter)) {
                $query->where('regional_user_id', $regionalUserIdFilter);
            }
            return $query->pluck('id_valora_mas')->toArray();
        }

        if ($roleType === 'regional') {
            return $this->sucursalesRegionales()
                        ->whereNotNull('id_valora_mas')
                        ->pluck('id_valora_mas')
                        ->toArray();
        }

        if ($roleType === 'gerente') {
            if ($this->sucursal && !is_null($this->sucursal->id_valora_mas)) {
                return [$this->sucursal->id_valora_mas];
            }
        }

        if ($roleType === 'modulo_user') {
            if ($this->sucursal && !is_null($this->sucursal->id_valora_mas)) {
                return [$this->sucursal->id_valora_mas];
            }
            $query = Sucursal::whereNotNull('id_valora_mas');
            if (!empty($regionalUserIdFilter)) {
                $query->where('regional_user_id', $regionalUserIdFilter);
            }
            return $query->pluck('id_valora_mas')->toArray();
        }

        // Sin rol asignado: Arreglo vacío (acceso denegado / 0 sucursales)
        return [];
    }

    /**
     * Obtener la colección de modelos Sucursal permitidos para Power BI.
     * @param int|string|null $regionalUserIdFilter
     */
    public function getPbiPermittedSucursales($regionalUserIdFilter = null)
    {
        $ids = $this->getPbiPermittedSucursalIds($regionalUserIdFilter);
        if (empty($ids)) {
            return collect();
        }

        return Sucursal::whereIn('id_valora_mas', $ids)->get();
    }
}