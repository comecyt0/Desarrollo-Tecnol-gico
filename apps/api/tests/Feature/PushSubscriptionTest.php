<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $this->user = User::factory()->create(['rol_id' => 4]);
        $this->token = JWTAuth::fromUser($this->user);
    }

    public function test_vapid_public_key_endpoint_responde(): void
    {
        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->getJson('/api/push/vapid-public-key');
        $res->assertOk()->assertJsonStructure(['public_key', 'configured']);
    }

    public function test_subscribe_guarda_la_suscripcion(): void
    {
        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123',
            'keys' => [
                'p256dh' => 'BNs...keyBase64',
                'auth' => 'Auth...Base64',
            ],
        ];

        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/push/subscribe', $payload)
            ->assertCreated()
            ->assertJsonStructure(['subscription_id']);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->user->id,
            'endpoint' => $payload['endpoint'],
        ]);
    }

    public function test_subscribe_es_idempotente_por_endpoint(): void
    {
        $payload = [
            'endpoint' => 'https://fcm.googleapis.com/x',
            'keys' => ['p256dh' => 'k1', 'auth' => 'a1'],
        ];
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->postJson('/api/push/subscribe', $payload);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->postJson('/api/push/subscribe', $payload);

        $this->assertEquals(1, PushSubscription::where('user_id', $this->user->id)->count());
    }

    public function test_unsubscribe_elimina(): void
    {
        $endpoint = 'https://fcm.googleapis.com/y';
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/push/subscribe', [
                'endpoint' => $endpoint,
                'keys' => ['p256dh' => 'k', 'auth' => 'a'],
            ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])
            ->postJson('/api/push/unsubscribe', ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJsonPath('deleted', 1);

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => $endpoint]);
    }

    public function test_subscribe_requiere_autenticacion(): void
    {
        $this->postJson('/api/push/subscribe', [
            'endpoint' => 'x',
            'keys' => ['p256dh' => 'x', 'auth' => 'x'],
        ])->assertUnauthorized();
    }
}
