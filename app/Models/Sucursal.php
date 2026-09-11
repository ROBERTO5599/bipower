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
        'id_sucursal_tarjeta',
        'longitud',
        'latitud',
    ];

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
