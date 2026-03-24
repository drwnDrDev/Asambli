<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Copropietario extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'user_id', 'tipo_documento', 'numero_documento',
        'es_residente', 'es_externo', 'empresa', 'telefono', 'activo',
    ];

    protected $casts = [
        'es_residente' => 'boolean',
        'es_externo'   => 'boolean',
        'activo'       => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unidades()
    {
        return $this->hasMany(Unidad::class);
    }

    public function poderesComoApoderado()
    {
        return $this->hasMany(Poder::class, 'apoderado_id');
    }

    public function poderesOtorgados()
    {
        return $this->hasMany(Poder::class, 'poderdante_id');
    }

    public function scopeExterno($query)
    {
        return $query->where('es_externo', true);
    }
}
