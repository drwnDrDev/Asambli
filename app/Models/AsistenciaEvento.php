<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AsistenciaEvento extends Model
{
    use BelongsToTenant;

    protected $table = 'asistencia_eventos';

    public const UPDATED_AT = null;

    protected $fillable = [
        'tenant_id', 'reunion_id', 'copropietario_id',
        'tipo', 'origen', 'quorum_resultante',
    ];

    protected $casts = [
        'quorum_resultante' => 'float',
    ];

    public function copropietario(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Copropietario::class);
    }
}
