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

    // 👇 Esto le dice a Laravel que el "username" es nick_name
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
     * Roles asignados al usuario.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role', 'user_id', 'role_id');
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
     * Determinar el tipo de rol de Power BI para el usuario basándose EXCLUSIVAMENTE en la tabla de roles.
     * @return string|null 'super_admin' | 'regional' | 'gerente' | null
     */
    public function getPbiRoleType()
    {
        $userRoles = $this->roles()->get();
        $roleNames = $userRoles->pluck('nombre')->toArray();

        if (in_array('PBI Super Administrador', $roleNames) || $userRoles->contains('id', 77)) {
            return 'super_admin';
        }

        if (in_array('PBI Regional', $roleNames) || $userRoles->contains('id', 78)) {
            return 'regional';
        }

        if (in_array('PBI Gerente', $roleNames) || $userRoles->contains('id', 79)) {
            return 'gerente';
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
