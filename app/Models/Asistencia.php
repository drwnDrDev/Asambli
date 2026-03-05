<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $fillable = [
        'reunion_id', 'copropietario_id', 'confirmada_por_admin',
        'hora_confirmacion', 'vota_por_poderes',
    ];

    protected $casts = [
        'confirmada_por_admin' => 'boolean',
        'hora_confirmacion' => 'datetime',
        'vota_por_poderes' => 'array',
    ];

    public function copropietario()
    {
        return $this->belongsTo(Copropietario::class);
    }
}
