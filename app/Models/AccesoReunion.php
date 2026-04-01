<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AccesoReunion extends Model
{
    use HasFactory;

    protected $table = 'acceso_reunion';

    protected $fillable = [
        'copropietario_id',
        'reunion_id',
        'pin_hash',
        'pin_plain',
        'session_token',
        'last_activity_at',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    protected $hidden = ['pin_hash', 'session_token'];

    public function copropietario(): BelongsTo
    {
        return $this->belongsTo(Copropietario::class);
    }

    public function reunion(): BelongsTo
    {
        return $this->belongsTo(Reunion::class);
    }

    /**
     * Genera un nuevo session_token, invalida el anterior.
     * Retorna el token en claro (para guardarlo en sesión del browser).
     */
    public function rotarToken(): string
    {
        $token = Str::random(64);
        $this->update([
            'session_token' => $token,
            'last_activity_at' => now(),
        ]);
        return $token;
    }

    public function verificarPin(string $pinEnClaro): bool
    {
        return password_verify($pinEnClaro, $this->pin_hash);
    }

    public static function generarPin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
