<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\MagicLinkService;

test('magic link is created for user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $service = app(MagicLinkService::class);

    $link = $service->generate($user);

    expect($link)->toContain('/acceso/');
    expect($user->magicLinks()->count())->toBe(1);
});

test('magic link without reunion redirects to login', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $service = app(MagicLinkService::class);
    $service->generate($user); // generates link without reunion_id

    $magicLink = $user->magicLinks()->first();

    $response = $this->get('/acceso/' . $magicLink->token);
    $response->assertRedirect('/login');
});

test('magic link with reunion_id redirects to sala login', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $reunion = \App\Models\Reunion::factory()->for($tenant)->create();
    $service = app(MagicLinkService::class);
    $service->generate($user, $reunion->id);

    $magicLink = $user->magicLinks()->first();

    $response = $this->get('/acceso/' . $magicLink->token);
    $response->assertRedirect("/sala/login/{$reunion->id}");
});
