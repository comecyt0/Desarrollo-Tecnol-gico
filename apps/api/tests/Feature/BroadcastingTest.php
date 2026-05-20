<?php

namespace Tests\Feature;

use App\Events\NotificacionCreada;
use App\Models\NotificacionLog;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BroadcastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_notificacion_log_dispara_evento_notificacion_creada(): void
    {
        Event::fake([NotificacionCreada::class]);

        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $user = User::factory()->create(['rol_id' => 4]);

        $notif = NotificacionLog::create([
            'user_id' => $user->id,
            'correo_destino' => 'test@example.com',
            'asunto' => 'Test',
            'mensaje' => 'mensaje',
            'tipo' => 'info',
            'enviado' => true,
        ]);

        Event::assertDispatched(NotificacionCreada::class, function ($event) use ($notif) {
            return $event->notificacion->id === $notif->id;
        });
    }

    public function test_evento_notificacion_creada_apunta_a_canal_privado_del_usuario(): void
    {
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $user = User::factory()->create(['rol_id' => 4]);

        $notif = NotificacionLog::create([
            'user_id' => $user->id,
            'correo_destino' => 'test@example.com',
            'asunto' => 'Test',
            'mensaje' => 'mensaje',
            'tipo' => 'info',
            'enviado' => true,
        ]);

        $event = new NotificacionCreada($notif);
        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertEquals('private-user.'.$user->id, $channels[0]->name);
        $this->assertEquals('notification.created', $event->broadcastAs());
        $this->assertArrayHasKey('asunto', $event->broadcastWith());
    }

    public function test_no_dispara_evento_si_no_hay_user_id(): void
    {
        Event::fake([NotificacionCreada::class]);

        NotificacionLog::create([
            'user_id' => null,
            'correo_destino' => 'sistema@local',
            'asunto' => 'Sin destinatario',
            'mensaje' => 'mensaje',
            'tipo' => 'sistema',
            'enviado' => true,
        ]);

        Event::assertNotDispatched(NotificacionCreada::class);
    }
}
