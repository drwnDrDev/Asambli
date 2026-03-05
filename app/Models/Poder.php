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
        'documento_url', 'registrado_por',
    ];

    protected static function booted(): void
    {
        static::creating(function ($poder) {
            $tenant = app('current_tenant');
            $maxPoderes = $tenant->max_poderes_por_delegado;

            $count = static::withoutGlobalScopes()
                ->where('reunion_id', $poder->reunion_id)
                ->where('apoderado_id', $poder->apoderado_id)
                ->count();

            if ($count >= $maxPoderes) {
                throw new \Exception(
                    "Este apoderado ya tiene el máximo de {$maxPoderes} poderes en esta reunión."
                );
            }
        });
    }
}
