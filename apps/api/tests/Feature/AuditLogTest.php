<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Convocatoria;
use App\Models\Empresa;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\TipoPrograma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_aprobacion_documental_genera_audit_log(): void
    {
        $rolRevisor = Rol::updateOrCreate(['id' => 2], ['nombre' => 'Revisor', 'slug' => 'revisor']);
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);

        $revisor = User::factory()->create(['rol_id' => 2]);
        $empresa = Empresa::factory()->create();
        $solicitante = User::factory()->create(['rol_id' => 4, 'empresa_id' => $empresa->id]);
        $tp = TipoPrograma::factory()->create();
        $conv = Convocatoria::factory()->create(['tipo_programa_id' => $tp->id]);

        $solicitud = Solicitud::create([
            'folio' => 'AUDIT-1',
            'user_id' => $solicitante->id,
            'empresa_id' => $empresa->id,
            'convocatoria_id' => $conv->id,
            'titulo_proyecto' => 'Proyecto audit',
            'descripcion_proyecto' => 'desc',
            'monto_solicitado' => 30000,
            'estado' => 'enviada',
        ]);

        $token = JWTAuth::fromUser($revisor);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson("/api/revisor/solicitudes/{$solicitud->id}/aprobar")
            ->assertOk();

        $log = AuditLog::where('action', 'solicitud.aprobada_documentalmente')->first();
        $this->assertNotNull($log);
        $this->assertEquals($revisor->id, $log->user_id);
        $this->assertEquals($solicitud->id, $log->subject_id);
        $this->assertEquals('enviada', $log->metadata['estado_previo']);
    }

    public function test_admin_puede_listar_audit_logs(): void
    {
        Rol::updateOrCreate(['id' => 1], ['nombre' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create(['rol_id' => 1]);
        $token = JWTAuth::fromUser($admin);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'test.action',
            'metadata' => ['x' => 1],
        ]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/audit-logs')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'test.action');
    }

    public function test_audit_log_action_filter_funciona(): void
    {
        Rol::updateOrCreate(['id' => 1], ['nombre' => 'Admin', 'slug' => 'admin']);
        $admin = User::factory()->create(['rol_id' => 1]);
        $token = JWTAuth::fromUser($admin);

        AuditLog::create(['user_id' => $admin->id, 'action' => 'solicitud.aprobada', 'metadata' => []]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'convenio.generado', 'metadata' => []]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/admin/audit-logs?action=convenio')
            ->assertOk();
        $this->assertCount(1, $res->json('data'));
    }
}
