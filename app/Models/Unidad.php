<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    /** @use HasFactory<\Database\Factories\UnidadFactory> */
    use HasFactory, BelongsToTenant;

    protected $table = 'unidades';

    protected $fillable = [
        'tenant_id', 'numero', 'tipo', 'coeficiente', 'torre', 'piso', 'activo',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:5',
        'activo' => 'boolean',
    ];

    public function copropietarios()
    {
        return $this->hasMany(Copropietario::class);
    }
}
