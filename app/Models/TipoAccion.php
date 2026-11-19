<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TipoAccion extends Model
{
    use HasFactory;

    protected $table = 'tipo_accion';

    protected $fillable = [
        'nombre',
        'status',
    ];

    public function movimientos()
    {
        return $this->hasMany(MovimientoTarjeta::class, 'tipo_accion_id');
    }
}
