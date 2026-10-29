<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RolePowerBi extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $table = 'roles_powerbi';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'modulo',
        'descripcion'
    ];

    /**
     * Usuarios asignados a este rol de Power BI
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'roles_user_powerbi', 'role_powerbi_id', 'user_id')
                    ->withTimestamps();
    }
}
