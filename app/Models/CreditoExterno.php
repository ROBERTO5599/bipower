<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CreditoExterno extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'creditos_externos';

    protected $fillable = [
        'user_id',
        'colaborador_nombre',
        'cliente_id',
        'comprador',
        'sucursal_id',
        'articulo_id',
        'articulo',
        'precio_prestamo',
        'precio_venta',
        'monto_credito',
        'plazo',
        'metodo_pago',
        'forma_pago',
        'evidencia_path',
        'evidencia_nombre_original',
        'evidencia_tamano',
        'evidencia_mime',
        'observaciones',
        'estado',
    ];

    protected $casts = [
        'precio_prestamo' => 'decimal:2',
        'precio_venta'    => 'decimal:2',
        'monto_credito'   => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function getEvidenciaUrlAttribute()
    {
        if (!$this->evidencia_path) {
            return null;
        }

        $relativePath = str_replace('public/', '', $this->evidencia_path);
        return asset('storage/' . $relativePath);
    }

    public function isPdf(): bool
    {
        if ($this->evidencia_mime === 'application/pdf') {
            return true;
        }
        return str_ends_with(strtolower($this->evidencia_path ?? ''), '.pdf');
    }

    public function isImage(): bool
    {
        if (str_starts_with($this->evidencia_mime ?? '', 'image/')) {
            return true;
        }
        $ext = strtolower(pathinfo($this->evidencia_path ?? '', PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    }
}

