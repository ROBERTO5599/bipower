<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Caminado extends Model
{
    use HasFactory;

    protected $table = 'caminados';

    protected $fillable = [
        'sucursal_id',
        'user_id',
        'fecha_recepcion',
        'fecha_revision',
        'total_articulos',
        'total_revisados',
        'total_sobrevaluados',
        'monto_total_prestamo',
        'monto_total_sobrevaluo',
        'observaciones',
        'estatus',
    ];

    protected $casts = [
        'fecha_recepcion' => 'date',
        'fecha_revision' => 'date',
        'monto_total_prestamo' => 'decimal:2',
        'monto_total_sobrevaluo' => 'decimal:2',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(CaminadoDetalle::class, 'caminado_id');
    }
}
