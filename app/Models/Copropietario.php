<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Copropietario extends Model
{
    use HasFactory, BelongsToTenant, Notifiable;

    protected $fillable = [
        'tenant_id', 'user_id', 'nombre', 'tipo_documento', 'numero_documento',
        'es_residente', 'es_externo', 'empresa', 'telefono', 'activo', 'email',
    ];

    protected $casts = [
        'es_residente' => 'boolean',
        'es_externo'   => 'boolean',
        'activo'       => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function unidades()
    {
        return $this->hasMany(Unidad::class);
    }

    public function poderesComoApoderado()
    {
        return $this->hasMany(Poder::class, 'apoderado_id');
    }

    public function ultimoPoderComoApoderado()
    {
        return $this->hasOne(\App\Models\Poder::class, 'apoderado_id')->latestOfMany();
    }

    public function poderesOtorgados()
    {
        return $this->hasMany(Poder::class, 'poderdante_id');
    }

    public function routeNotificationForMail(): string
    {
        return $this->email ?? '';
    }

    public function accesos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AccesoReunion::class);
    }

    public function accesoParaReunion(int $reunionId): ?\App\Models\AccesoReunion
    {
        return $this->accesos()
            ->where('reunion_id', $reunionId)
            ->where('activo', true)
            ->first();
    }
}
