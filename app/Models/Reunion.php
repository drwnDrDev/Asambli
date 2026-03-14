<?php

namespace App\Models;

use App\Enums\ReunionEstado;
use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reunion extends Model
{
    /** @use HasFactory<\Database\Factories\ReunionFactory> */
    use HasFactory, BelongsToTenant;

    protected $table = 'reuniones';

    protected $fillable = [
        'tenant_id', 'titulo', 'tipo', 'tipo_voto_peso',
        'quorum_requerido', 'estado', 'fecha_programada',
        'fecha_inicio', 'fecha_fin', 'convocatoria_enviada_at', 'creado_por',
        'qr_token', 'qr_expires_at',
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'convocatoria_enviada_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'quorum_requerido' => 'decimal:2',
        'estado' => ReunionEstado::class,
    ];

    public function logs()
    {
        return $this->hasMany(ReunionLog::class);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function votaciones()
    {
        return $this->hasMany(Votacion::class);
    }

    public function estaActiva(): bool
    {
        return !$this->estado->esTerminal();
    }
}
