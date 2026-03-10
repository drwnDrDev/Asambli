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
        'es_residente', 'telefono', 'activo',
    ];

    protected $casts = [
        'es_residente' => 'boolean',
        'activo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unidades()
    {
        return $this->hasMany(Unidad::class);
    }
}
