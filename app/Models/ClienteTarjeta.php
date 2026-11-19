<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteTarjeta extends Model
{
    use HasFactory;

    protected $table = 'clientes_tarjeta';

    protected $fillable = [
        'nombre',
        'primer_apellido',
        'segundo_apellido',
        'correo',
        'telefono',
        'codigo_verificacion',
        'codigo_verificacion_expira_en',
        'user_id_creacion',
        'user_id_convencimiento',
        'tarjeta_id',
        'verificado',
        'status',
    ];

    protected $dates = [
        'codigo_verificacion_expira_en',
        'created_at',
        'updated_at',
    ];

    public function movimientosTarjeta()
    {
        return $this->hasMany(MovimientoTarjeta::class, 'cliente_tarjeta_id');
    }

    public function usuarioConvencimiento()
    {
        return $this->belongsTo(User::class, 'user_id_convencimiento');
    }

    public function usuarioCreacion()
    {
        return $this->belongsTo(User::class, 'user_id_creacion');
    }

    public function stockTarjeta()
    {
        return $this->belongsTo(StockTarjeta::class, 'tarjeta_id');
    }

    public function getNombreCompletoAttribute()
    {
        return trim("{$this->nombre} {$this->primer_apellido} {$this->segundo_apellido}");
    }
}
