<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CaminadoDetalle extends Model
{
    use HasFactory;

    protected $table = 'caminado_detalles';

    protected $fillable = [
        'caminado_id',
        'folio',
        'contrato',
        'cod_contrato',
        'item_id',
        'id_intranet',
        'descripcion',
        'usuario',
        'articulo',
        'categoria',
        'familia',
        'es_familia_abierta',
        'monto',
        'ubicacion',
        'pct_adicional',
        'prestamo_real',
        'folio_cupon',
        'estado_articulo',
        'descripcion_correcta',
        'accesorios_completos',
        'sobrevaluo',
        'monto_sobrevaluo',
        'pct_sobrevaluo',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'pct_adicional' => 'decimal:2',
        'prestamo_real' => 'decimal:2',
        'monto_sobrevaluo' => 'decimal:2',
        'pct_sobrevaluo' => 'decimal:2',
        'descripcion_correcta' => 'boolean',
        'accesorios_completos' => 'boolean',
        'sobrevaluo' => 'boolean',
        'es_familia_abierta' => 'boolean',
    ];

    public function caminado()
    {
        return $this->belongsTo(Caminado::class, 'caminado_id');
    }
}
