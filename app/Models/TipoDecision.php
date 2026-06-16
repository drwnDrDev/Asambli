<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoDecision extends Model
{
    protected $table = 'tipos_decision';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'tipo_mayoria', 'aplica_en', 'orden',
    ];

    protected $casts = [
        'aplica_en' => 'array',
    ];

    public static function paraAsamblea(): \Illuminate\Database\Eloquent\Collection
    {
        return static::whereJsonContains('aplica_en', 'asamblea')->orderBy('orden')->get();
    }
}
