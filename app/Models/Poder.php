<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Poder extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'poderes';

    protected $fillable = [
        'tenant_id', 'reunion_id', 'apoderado_id', 'poderdante_id',
        'documento_url', 'registrado_por', 'estado',
        'aprobado_por', 'rechazado_motivo', 'invitacion_enviada_at',
    ];

    // Estados posibles: pendiente, aprobado, rechazado, revocado, expirado

    protected $casts = [
        'invitacion_enviada_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($poder) {
            // Validar: un solo poder activo por poderdante
            $yaActivo = static::withoutGlobalScopes()
                ->where('poderdante_id', $poder->poderdante_id)
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->exists();

            if ($yaActivo) {
                throw new \Exception('Este copropietario ya tiene un poder activo.');
            }

            // Validar: el apoderado no supera el máximo de poderes recibidos
            $tenant = app('current_tenant');
            $maxPoderes = $tenant->max_poderes_por_delegado ?? 2;

            $countApoderado = static::withoutGlobalScopes()
                ->where('apoderado_id', $poder->apoderado_id)
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->count();

            if ($countApoderado >= $maxPoderes) {
                throw new \Exception(
                    "Este delegado ya tiene el máximo de {$maxPoderes} poderes activos."
                );
            }
        });
    }

    public function reunion()
    {
        return $this->belongsTo(Reunion::class);
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
