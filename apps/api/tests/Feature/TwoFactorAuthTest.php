<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private Google2FA $g2fa;

    protected function setUp(): void
    {
        parent::setUp();
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $this->user = User::factory()->create(['rol_id' => 4, 'password' => bcrypt('s3cret-pass!')]);
        $this->token = JWTAuth::fromUser($this->user);
        $this->g2fa = new Google2FA;
    }

    public function test_setup_genera_secret_y_qr_svg(): void
    {
        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/auth/2fa/setup');

        $res->assertOk()
            ->assertJsonStructure(['secret', 'qr_svg', 'otp_uri']);
        $this->assertNotNull($this->user->fresh()->two_factor_secret);
        $this->assertNull($this->user->fresh()->two_factor_confirmed_at);
    }

    public function test_confirm_activa_2fa_con_codigo_valido(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/auth/2fa/setup')->assertOk();

        $secret = $this->user->fresh()->two_factor_secret;
        $code = $this->g2fa->getCurrentOtp($secret);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/auth/2fa/confirm', ['code' => $code]);

        $res->assertOk()->assertJsonStructure(['recovery_codes']);
        $this->assertCount(8, $res->json('recovery_codes'));
        $this->assertNotNull($this->user->fresh()->two_factor_confirmed_at);
    }

    public function test_confirm_rechaza_codigo_invalido(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/auth/2fa/setup')->assertOk();

        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/auth/2fa/confirm', ['code' => '000000'])
            ->assertStatus(422);
    }

    public function test_login_con_2fa_devuelve_challenge_y_no_jwt(): void
    {
        $this->enableTwoFactor();

        $res = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 's3cret-pass!',
        ]);

        $res->assertOk()
            ->assertJsonPath('requires_2fa', true)
            ->assertJsonStructure(['challenge_id']);
        $res->assertJsonMissing(['token_type' => 'bearer']);
    }

    public function test_challenge_acepta_codigo_totp_valido_y_emite_jwt(): void
    {
        $secret = $this->enableTwoFactor();
        $login = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 's3cret-pass!',
        ])->assertOk();

        $challengeId = $login->json('challenge_id');
        $code = $this->g2fa->getCurrentOtp($secret);

        $res = $this->postJson('/api/auth/2fa/challenge', [
            'challenge_id' => $challengeId,
            'code' => $code,
        ]);

        $res->assertOk()
            ->assertJsonStructure(['token_type', 'expires_in', 'user']);
    }

    public function test_challenge_acepta_recovery_code_y_lo_invalida(): void
    {
        $this->enableTwoFactor();
        $codes = $this->user->fresh()->two_factor_recovery_codes;
        $recovery = $codes[0];

        $login = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 's3cret-pass!',
        ])->assertOk();

        $res = $this->postJson('/api/auth/2fa/challenge', [
            'challenge_id' => $login->json('challenge_id'),
            'code' => $recovery,
        ]);
        $res->assertOk();

        // El código de recuperación ya no debe estar disponible
        $this->assertNotContains($recovery, $this->user->fresh()->two_factor_recovery_codes);
    }

    public function test_challenge_rechaza_codigo_invalido(): void
    {
        $this->enableTwoFactor();
        $login = $this->postJson('/api/auth/login', [
            'email' => $this->user->email,
            'password' => 's3cret-pass!',
        ])->assertOk();

        $this->postJson('/api/auth/2fa/challenge', [
            'challenge_id' => $login->json('challenge_id'),
            'code' => '000000',
        ])->assertStatus(422);
    }

    public function test_disable_requiere_password_y_codigo_vigente(): void
    {
        $secret = $this->enableTwoFactor();
        $code = $this->g2fa->getCurrentOtp($secret);

        // Sin password: 422
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/auth/2fa/disable', ['code' => $code, 'password' => 'wrong'])
            ->assertStatus(422);

        // Con password + code correctos
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/auth/2fa/disable', ['code' => $code, 'password' => 's3cret-pass!'])
            ->assertOk();
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }

    public function test_me_expone_two_factor_enabled_false_por_defecto(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('two_factor_enabled', false);
    }

    public function test_me_expone_two_factor_enabled_true_cuando_esta_activo(): void
    {
        $this->enableTwoFactor();
        // Tomamos al usuario fresco para que el JWT incluya el estado correcto
        $fresh = $this->user->fresh();
        $this->assertTrue($fresh->hasTwoFactorEnabled(), 'precondición: 2FA debe estar activo en DB');
        $token = JWTAuth::fromUser($fresh);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('two_factor_enabled', true);
    }

    private function enableTwoFactor(): string
    {
        $secret = $this->g2fa->generateSecretKey();
        $this->user->two_factor_secret = $secret;
        $this->user->two_factor_recovery_codes = ['recovery-001', 'recovery-002'];
        $this->user->two_factor_confirmed_at = now();
        $this->user->save();

        return $secret;
    }
}
