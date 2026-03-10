<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unidad extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'unidades';

    protected $fillable = [
        'tenant_id', 'copropietario_id', 'numero', 'tipo', 'coeficiente', 'torre', 'piso', 'activo',
    ];

    protected $casts = [
        'coeficiente' => 'decimal:5',
        'activo' => 'boolean',
    ];

    public function copropietario()
    {
        return $this->belongsTo(Copropietario::class);
    }
}
