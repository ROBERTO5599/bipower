<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SucursalConfiguracionTarjeta extends Model
{
    use HasFactory;

    protected $table = 'sucursal_configuracion_tarjetas';

    protected $fillable = [
        'sucursal_id',
        'id_sucursal_tarjeta',
        'comision_clip',
        'iva_comision',
        'comision_meses_3',
        'comision_meses_6',
        'comision_meses_9',
        'comision_meses_12',
        'comision_meses_18',
        'comision_meses_24',
    ];

    /**
     * Get the sucursal that owns the card configuration.
     */
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
