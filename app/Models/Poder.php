<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Poder extends Model
{
    use BelongsToTenant;

    protected $table = 'poderes';

    protected $fillable = [
        'tenant_id', 'reunion_id', 'apoderado_id', 'poderdante_id',
        'documento_url', 'registrado_por', 'estado',
        'aprobado_por', 'rechazado_motivo', 'invitacion_enviada_at',
    ];

    protected $casts = [
        'invitacion_enviada_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($poder) {
            $tenant = app('current_tenant');
            $maxPoderes = $tenant->max_poderes_por_delegado;

            $count = static::withoutGlobalScopes()
                ->where('reunion_id', $poder->reunion_id)
                ->where('apoderado_id', $poder->apoderado_id)
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->count();

            if ($count >= $maxPoderes) {
                throw new \Exception(
                    "Este apoderado ya tiene el máximo de {$maxPoderes} poderes en esta reunión."
                );
            }
        });
    }

    public function apoderado()
    {
        return $this->belongsTo(Copropietario::class, 'apoderado_id');
    }

    public function poderdante()
    {
        return $this->belongsTo(Copropietario::class, 'poderdante_id');
    }

    public function registradoPor()
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function scopePendiente($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeAprobado($query)
    {
        return $query->where('estado', 'aprobado');
    }
}
