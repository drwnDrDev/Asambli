<?php

use App\Models\Tenant;

test('tenant can be created with required fields', function () {
    $tenant = Tenant::factory()->create([
        'nombre' => 'Conjunto Residencial El Prado',
        'nit' => '900123456-1',
    ]);

    expect($tenant->nombre)->toBe('Conjunto Residencial El Prado');
    expect($tenant->max_poderes_por_delegado)->toBe(2); // default
    expect($tenant->activo)->toBeTrue(); // default
});
