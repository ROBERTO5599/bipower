<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'nombre',
        'primer_apellido',
        'segundo_apellido',
        'nick_name',
        'password',
        'sucursal_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // 👇 Esto le dice a Laravel que el "username" es nick_name
    public function getAuthIdentifierName()
    {
        return 'nick_name';
    }

    // Nombre completo accesible como $user->nombre_completo
    public function getNombreCompletoAttribute()
    {
        return "{$this->nombre} {$this->primer_apellido} {$this->segundo_apellido}";
    }
    public function username()
    {
        return 'nick_name';
    }

    // Relación con sucursal
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
