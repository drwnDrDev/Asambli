<?php

namespace App\Models;

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
    ];

    protected $casts = [
        'fecha_programada' => 'datetime',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'convocatoria_enviada_at' => 'datetime',
        'quorum_requerido' => 'decimal:2',
    ];

    public function logs()
    {
        return $this->hasMany(ReunionLog::class);
    }

    public function asistencia()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function votaciones()
    {
        return $this->hasMany(Votacion::class);
    }

    public function transicionarA(string $nuevoEstado, User $user, array $metadata = []): void
    {
        $estadoAnterior = $this->estado;
        $this->update(['estado' => $nuevoEstado]);

        ReunionLog::create([
            'reunion_id' => $this->id,
            'user_id' => $user->id,
            'accion' => "estado_cambiado_a_{$nuevoEstado}",
            'metadata' => array_merge($metadata, ['estado_anterior' => $estadoAnterior]),
        ]);

        if ($nuevoEstado === 'en_curso') {
            $this->update(['fecha_inicio' => now()]);
        }

        if ($nuevoEstado === 'finalizada') {
            $this->update(['fecha_fin' => now()]);
        }
    }
}
