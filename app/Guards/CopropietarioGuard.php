<?php
namespace App\Guards;

use App\Models\AccesoReunion;
use App\Models\Copropietario;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

class CopropietarioGuard implements Guard
{
    protected ?Copropietario $copropietario = null;
    protected ?AccesoReunion $acceso = null;

    public function __construct(protected Request $request) {}

    public function check(): bool
    {
        return $this->copropietario() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?Copropietario
    {
        return $this->copropietario();
    }

    public function copropietario(): ?Copropietario
    {
        if ($this->copropietario !== null) {
            return $this->copropietario;
        }

        if (!$this->request->hasSession()) {
            return null;
        }

        $token = $this->request->session()->get('copropietario_session_token');
        if (!$token) {
            return null;
        }

        // Buscar el acceso activo con este token
        // session_token está en $hidden — solo afecta serialización, no queries
        $acceso = AccesoReunion::with(['copropietario', 'reunion'])
            ->where('session_token', $token)
            ->where('activo', true)
            ->first();

        if (!$acceso) {
            return null;
        }

        // Verificar que la reunión siga activa
        $estadosActivos = ['ante_sala', 'en_curso', 'suspendida'];
        if (!in_array($acceso->reunion->estado->value, $estadosActivos)) {
            return null;
        }

        $this->acceso = $acceso;
        $this->copropietario = $acceso->copropietario;
        return $this->copropietario;
    }

    public function accesoActual(): ?AccesoReunion
    {
        $this->copropietario();
        return $this->acceso;
    }

    public function id(): mixed
    {
        return $this->copropietario()?->id;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->copropietario !== null;
    }

    public function setUser($user): static
    {
        $this->copropietario = $user;
        return $this;
    }
}
