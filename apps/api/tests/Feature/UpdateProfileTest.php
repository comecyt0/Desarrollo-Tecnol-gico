<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class UpdateProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicitante_puede_completar_su_perfil_de_onboarding(): void
    {
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create(['rol_id' => 4, 'empresa_id' => null]);
        $token = JWTAuth::fromUser($user);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/api/auth/profile', [
                'empresa_id' => $empresa->id,
                'cargo' => 'Investigadora',
                'telefono' => '722 123 4567',
            ]);

        $res->assertOk();
        $user->refresh();
        $this->assertEquals($empresa->id, $user->empresa_id);
        $this->assertEquals('Investigadora', $user->cargo);
        $this->assertEquals('722 123 4567', $user->telefono);
    }

    public function test_no_permite_cambiar_email_ni_rol_via_profile(): void
    {
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $user = User::factory()->create(['rol_id' => 4, 'email' => 'orig@x.mx']);
        $token = JWTAuth::fromUser($user);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/api/auth/profile', [
                'email' => 'hacker@x.mx',
                'rol_id' => 1,
                'cargo' => 'Investigador',
            ])
            ->assertOk();

        $user->refresh();
        $this->assertEquals('orig@x.mx', $user->email);
        $this->assertEquals(4, $user->rol_id);
        $this->assertEquals('Investigador', $user->cargo);
    }

    public function test_empresa_id_inexistente_devuelve_422(): void
    {
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $user = User::factory()->create(['rol_id' => 4]);
        $token = JWTAuth::fromUser($user);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->putJson('/api/auth/profile', [
                'empresa_id' => 99999,
            ])
            ->assertStatus(422);
    }

    public function test_requiere_autenticacion(): void
    {
        $this->putJson('/api/auth/profile', ['cargo' => 'X'])
            ->assertUnauthorized();
    }
}
