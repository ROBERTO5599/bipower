<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockTarjeta extends Model
{
    use HasFactory;

    protected $table = 'stocktarjetas';

    protected $fillable = [
        'codigo_tarjeta',
        'id_sucursal',
        'user_id',
        'status',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'id_sucursal');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function clienteTarjeta()
    {
        return $this->hasOne(ClienteTarjeta::class, 'tarjeta_id');
    }
}
