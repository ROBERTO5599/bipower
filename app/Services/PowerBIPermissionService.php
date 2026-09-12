<?php

namespace App\Services;

use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;

class PowerBIPermissionService
{
    /**
     * Get list of Regional Users for Super Admin filter dropdowns.
     * Returns users who are assigned as regional_user_id on at least one sucursal OR have role 'PBI Regional'.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getRegionales()
    {
        $assignedUserIds = Sucursal::whereNotNull('regional_user_id')
            ->pluck('regional_user_id')
            ->unique()
            ->filter()
            ->toArray();

        return User::whereIn('id', $assignedUserIds)
            ->orWhereHas('roles', function($q) {
                $q->where('nombre', 'PBI Regional')->orWhere('roles.id', 78);
            })
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Get permitted sucursal IDs for a given user or current authenticated user.
     *
     * @param User|null $user
     * @param int|string|null $regionalUserIdFilter
     * @return array
     */
    public static function getPermittedSucursalIds(?User $user = null, $regionalUserIdFilter = null): array
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return [];
        }

        return $user->getPbiPermittedSucursalIds($regionalUserIdFilter);
    }

    /**
     * Get permitted Sucursal Eloquent models for dropdowns/views.
     *
     * @param User|null $user
     * @param int|string|null $regionalUserIdFilter
     * @return \Illuminate\Support\Collection
     */
    public static function getPermittedSucursales(?User $user = null, $regionalUserIdFilter = null)
    {
        $user = $user ?: Auth::user();
        if (!$user) {
            return collect();
        }

        return $user->getPbiPermittedSucursales($regionalUserIdFilter);
    }

    /**
     * Check if a user can access a specific sucursal by its id_valora_mas.
     *
     * @param int|string $idValoraMas
     * @param User|null $user
     * @param int|string|null $regionalUserIdFilter
     * @return bool
     */
    public static function canAccessSucursal($idValoraMas, ?User $user = null, $regionalUserIdFilter = null): bool
    {
        $permittedIds = self::getPermittedSucursalIds($user, $regionalUserIdFilter);
        return in_array($idValoraMas, $permittedIds);
    }
}
