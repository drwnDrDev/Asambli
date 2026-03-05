<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voto extends Model
{
    use \App\Traits\BelongsToTenant;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id', 'votacion_id', 'copropietario_id', 'en_nombre_de',
        'opcion_id', 'peso', 'ip_address', 'user_agent', 'hash_verificacion', 'created_at',
    ];

    protected $casts = [
        'peso' => 'decimal:5',
        'created_at' => 'datetime',
    ];
}
