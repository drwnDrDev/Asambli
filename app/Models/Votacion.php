<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Votacion extends Model
{
    /** @use HasFactory<\Database\Factories\VotacionFactory> */
    use HasFactory, BelongsToTenant;

    protected $table = 'votaciones';

    protected $fillable = [
        'tenant_id', 'reunion_id', 'pregunta', 'descripcion',
        'tipo', 'es_secreta', 'estado', 'abierta_at', 'cerrada_at', 'creada_por',
    ];

    protected $casts = [
        'es_secreta' => 'boolean',
        'abierta_at' => 'datetime',
        'cerrada_at' => 'datetime',
    ];

    public function reunion()
    {
        return $this->belongsTo(Reunion::class);
    }

    public function opciones()
    {
        return $this->hasMany(OpcionVotacion::class)->orderBy('orden');
    }

    public function votos()
    {
        return $this->hasMany(Voto::class);
    }
}
