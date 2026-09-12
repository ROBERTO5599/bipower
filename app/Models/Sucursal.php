<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sucursal extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'id_valora_mas',
        'regional_user_id',
        'longitud',
        'latitud',
    ];

    /**
     * Get the regional user assigned to this sucursal.
     */
    public function regional()
    {
        return $this->belongsTo(User::class, 'regional_user_id');
    }

    /**
     * Get the card configuration for the sucursal.
     */
    public function configuracionTarjeta()
    {
        return $this->hasOne(SucursalConfiguracionTarjeta::class, 'sucursal_id');
    }

    /**
     * Scope query to only include SVM sucursales with valid id_valora_mas.
     */
    public function scopeSvm($query)
    {
        return $query->whereNotNull('id_valora_mas')->where('descripcion', 'SVM');
    }

    /**
     * Get the database name suffix for this sucursal.
     *
     * @return int|null
     */
    public function getDatabaseSuffixAttribute()
    {
        return $this->id_valora_mas;
    }
}
