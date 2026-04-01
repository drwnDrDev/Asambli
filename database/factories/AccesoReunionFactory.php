<?php
namespace Database\Factories;

use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Reunion;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccesoReunionFactory extends Factory
{
    protected $model = AccesoReunion::class;

    public function definition(): array
    {
        $pin = '123456';
        return [
            'copropietario_id' => Copropietario::factory(),
            'reunion_id'       => Reunion::factory(),
            'pin_hash'         => password_hash($pin, PASSWORD_BCRYPT),
            'pin_plain'        => $pin,
            'session_token'    => null,
            'last_activity_at' => null,
            'activo'           => true,
        ];
    }
}
