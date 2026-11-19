<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromocionMonedero extends Model
{
    use HasFactory;

    protected $table = 'promociones_monedero';

    protected $fillable = [
        'nombre',
        'tipo_campana',
        'factor_multiplicador',
        'monto_promocional',
        'fecha_inicio',
        'fecha_fin_acumulacion',
        'fecha_vencimiento',
        'fecha_limite_canje',
        'descripcion',
        'status',
        'user_id_creacion',
    ];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin_acumulacion' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'fecha_limite_canje' => 'datetime',
        'monto_promocional' => 'decimal:2',
        'factor_multiplicador' => 'decimal:2',
    ];

    public function creador()
    {
        return $this->belongsTo(User::class, 'user_id_creacion');
    }

    public function movimientosTarjeta()
    {
        return $this->hasMany(MovimientoTarjeta::class, 'promocion_id');
    }
}
