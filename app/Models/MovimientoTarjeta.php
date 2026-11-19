<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class MovimientoTarjeta extends Model
{
    use HasFactory;

    protected $table = 'movimientos_tarjeta';

    protected $fillable = [
        'cliente_tarjeta_id',
        'sucursal_id',
        'user_id',
        'codigo_movimiento',
        'contrato',
        'monto',
        'tipo_accion_id',
        'tipo_prenda',
        'puntos_generados',
        'puntos_base',
        'factor_multiplicador',
        'promocion_id',
        'status',
        'comentario_cancelacion',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'puntos_generados' => 'decimal:2',
        'puntos_base' => 'decimal:2',
        'factor_multiplicador' => 'decimal:2',
    ];

    public function cliente()
    {
        return $this->belongsTo(ClienteTarjeta::class, 'cliente_tarjeta_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function tipoAccion()
    {
        return $this->belongsTo(TipoAccion::class, 'tipo_accion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function promocion()
    {
        return $this->belongsTo(PromocionMonedero::class, 'promocion_id');
    }
}
