<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    protected $fillable = [
        'nombre', 'nit', 'direccion', 'ciudad',
        'logo_url', 'max_poderes_por_delegado', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'max_poderes_por_delegado' => 'integer',
    ];
}
