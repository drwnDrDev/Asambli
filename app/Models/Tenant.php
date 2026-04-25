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
        'logo_url', 'max_poderes_por_delegado', 'activo', 'producto',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'max_poderes_por_delegado' => 'integer',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function unidades()
    {
        return $this->hasMany(Unidad::class);
    }

    public function reuniones()
    {
        return $this->hasMany(Reunion::class);
    }

    public function administradores(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\TenantAdministrador::class)->where('activo', true);
    }
}
