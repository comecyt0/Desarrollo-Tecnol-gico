<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Convocatoria;
use App\Models\Institucion;
use App\Models\AreaConocimiento;
use App\Models\Rol;
use App\Models\ListaNegra;
use App\Models\SolicitudCampoDinamico;
use App\Models\SolicitudRubroPresupuesto;
use App\Models\SolicitudMiembroEquipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class SolicitudDinamicoTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Convocatoria $convocatoria;
    protected Institucion $institucion;
    protected Rol $rolSolicitante;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rolSolicitante = Rol::create(['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        AreaConocimiento::create(['nombre' => 'Ingeniería', 'activo' => true]);

        $this->institucion = Institucion::factory()->create();

        $this->user = User::factory()->create([
            'rol_id'         => $this->rolSolicitante->id,
            'institucion_id' => $this->institucion->id,
        ]);

        $this->convocatoria = Convocatoria::factory()->activa()->create();
        $this->token        = JWTAuth::fromUser($this->user);
    }

    private function authed()
    {
        return $this->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function basePayload(array $overrides = []): array
    {
        return array_merge([
            'convocatoria_id'      => $this->convocatoria->id,
            'titulo_proyecto'      => 'Proyecto de Prueba',
            'modalidad'            => 'Vinculación',
            'area_conocimiento_id' => 1,
            'descripcion'          => 'Descripción base del proyecto de prueba.',
            'monto_solicitado'     => 50000,
        ], $overrides);
    }

    // ===== BASIC CREATION TESTS =====

    public function test_store_creates_solicitud_in_borrador_state()
    {
        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('solicitud.estado', 'borrador');
    }

    public function test_store_generates_folio_in_correct_format()
    {
        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(201);
        $folio = $response->json('solicitud.folio');

        $this->assertMatchesRegularExpression('/^COMECYT-\d{4}-[A-Z0-9]{6}$/', $folio);
    }

    public function test_store_requires_authentication()
    {
        $response = $this->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(401);
    }

    // ===== VALIDATION TESTS =====

    public function test_store_fails_without_convocatoria_id()
    {
        $payload = $this->basePayload();
        unset($payload['convocatoria_id']);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(422);
    }

    public function test_store_fails_without_titulo_proyecto()
    {
        $payload = $this->basePayload();
        unset($payload['titulo_proyecto']);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(422);
    }

    public function test_store_fails_without_modalidad()
    {
        $payload = $this->basePayload();
        unset($payload['modalidad']);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(422);
    }

    public function test_store_fails_without_monto_solicitado()
    {
        $payload = $this->basePayload();
        unset($payload['monto_solicitado']);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(422);
    }

    public function test_store_fails_with_monto_zero()
    {
        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload(['monto_solicitado' => 0]));

        $response->assertStatus(422);
    }

    public function test_store_fails_when_convocatoria_is_closed()
    {
        $closedConvocatoria = Convocatoria::factory()->cerrada()->create();

        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload([
                'convocatoria_id' => $closedConvocatoria->id
            ]));

        $response->assertStatus(422);
    }

    // ===== INSTITUTION/BLACKLIST TESTS =====

    public function test_store_fails_when_user_has_no_institucion()
    {
        $userNoInst = User::factory()->create([
            'rol_id'         => 1,
            'institucion_id' => null,
        ]);

        $token = JWTAuth::fromUser($userNoInst);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(403)
            ->assertJsonPath('error', 'sin_institucion');
    }

    public function test_store_fails_when_institucion_is_in_lista_negra()
    {
        ListaNegra::factory()->create([
            'institucion_id' => $this->institucion->id,
            'activa'         => true,
        ]);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(403)
            ->assertJsonPath('error', 'institucion_bloqueada');
    }

    public function test_store_allows_when_lista_negra_entry_is_inactive()
    {
        ListaNegra::factory()->inactiva()->create([
            'institucion_id' => $this->institucion->id,
        ]);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(201);
    }

    // ===== DYNAMIC CAMPOS TESTS =====

    public function test_store_persists_campos_dinamicos()
    {
        $payload = $this->basePayload([
            'campos_dinamicos' => [
                ['campo_id' => 1, 'valor' => 'Valor campo 1'],
                ['campo_id' => 2, 'valor' => 'Valor campo 2'],
            ]
        ]);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(201);
        $solicitudId = $response->json('solicitud.id');

        $this->assertDatabaseCount('solicitud_campos_dinamicos', 2);
        $this->assertDatabaseHas('solicitud_campos_dinamicos', [
            'solicitud_id'      => $solicitudId,
            'programa_campo_id' => 1,
            'valor_texto'       => 'Valor campo 1',
        ]);
        $this->assertDatabaseHas('solicitud_campos_dinamicos', [
            'solicitud_id'      => $solicitudId,
            'programa_campo_id' => 2,
            'valor_texto'       => 'Valor campo 2',
        ]);
    }

    public function test_store_skips_campos_dinamicos_when_absent()
    {
        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(201);

        $this->assertDatabaseCount('solicitud_campos_dinamicos', 0);
    }

    // ===== DYNAMIC RUBROS TESTS =====

    public function test_store_persists_rubros_with_positive_monto()
    {
        $payload = $this->basePayload([
            'rubros' => [
                ['rubro_id' => 1, 'monto' => 1000],
                ['rubro_id' => 2, 'monto' => 0],      // Should be skipped
                ['rubro_id' => 3, 'monto' => 500],
            ]
        ]);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(201);
        $solicitudId = $response->json('solicitud.id');

        // Only 2 rubros with positive monto should be persisted
        $this->assertDatabaseCount('solicitud_rubros_presupuesto', 2);
        $this->assertDatabaseHas('solicitud_rubros_presupuesto', [
            'solicitud_id'     => $solicitudId,
            'rubro_id'         => 1,
            'monto_solicitado' => 1000,
        ]);
        $this->assertDatabaseHas('solicitud_rubros_presupuesto', [
            'solicitud_id'     => $solicitudId,
            'rubro_id'         => 3,
            'monto_solicitado' => 500,
        ]);
    }

    public function test_store_skips_rubro_with_zero_monto()
    {
        $payload = $this->basePayload([
            'rubros' => [
                ['rubro_id' => 1, 'monto' => 0],
            ]
        ]);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseCount('solicitud_rubros_presupuesto', 0);
    }

    public function test_store_skips_rubros_when_absent()
    {
        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(201);

        $this->assertDatabaseCount('solicitud_rubros_presupuesto', 0);
    }

    // ===== DYNAMIC MIEMBROS EQUIPO TESTS =====

    public function test_store_persists_miembros_equipo()
    {
        $payload = $this->basePayload([
            'miembros_equipo' => [
                ['nombre' => 'Juan Pérez', 'edad' => 25, 'rol' => 'Líder', 'email' => 'juan@example.com'],
                ['nombre' => 'María García', 'edad' => 28, 'rol' => 'Desarrolladora', 'email' => 'maria@example.com'],
                ['nombre' => 'Carlos López', 'edad' => 30, 'rol' => 'Asesor', 'email' => 'carlos@example.com'],
            ]
        ]);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(201);
        $solicitudId = $response->json('solicitud.id');

        $this->assertDatabaseCount('solicitud_miembros_equipo', 3);
        $this->assertDatabaseHas('solicitud_miembros_equipo', [
            'solicitud_id'    => $solicitudId,
            'nombre_completo' => 'Juan Pérez',
            'edad'            => 25,
            'rol_en_equipo'   => 'Líder',
            'correo'          => 'juan@example.com',
        ]);
    }

    public function test_first_miembro_is_marked_as_lider()
    {
        $payload = $this->basePayload([
            'miembros_equipo' => [
                ['nombre' => 'Primer Miembro', 'edad' => 25, 'rol' => 'Rol1', 'email' => 'first@example.com'],
                ['nombre' => 'Segundo Miembro', 'edad' => 28, 'rol' => 'Rol2', 'email' => 'second@example.com'],
            ]
        ]);

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(201);
        $solicitudId = $response->json('solicitud.id');

        $miembros = SolicitudMiembroEquipo::where('solicitud_id', $solicitudId)
            ->orderBy('id')
            ->get();

        $this->assertTrue($miembros[0]->es_lider);
        $this->assertFalse($miembros[1]->es_lider);
    }

    public function test_store_skips_miembros_when_absent()
    {
        $response = $this->authed()
            ->postJson('/api/solicitudes', $this->basePayload());

        $response->assertStatus(201);

        $this->assertDatabaseCount('solicitud_miembros_equipo', 0);
    }

    // ===== ACTIVE CONVOCATORIAS TESTS =====

    public function test_active_convocatorias_includes_tipo_programa_relation()
    {
        $programa = \App\Models\TipoPrograma::factory()->create();
        $convActiva = Convocatoria::factory()->activa()->create(['tipo_programa_id' => $programa->id]);
        $convCerrada = Convocatoria::factory()->cerrada()->create();

        $response = $this->authed()
            ->getJson('/api/solicitudes/convocatorias-activas');

        $response->assertStatus(200);
        $convocatorias = $response->json();

        // Should have at least 1 active (from setUp + new one = 2)
        $this->assertCount(2, $convocatorias);

        // Should include tipoPrograma relation on the new convocatoria
        $newConv = collect($convocatorias)->firstWhere('id', $convActiva->id);
        $this->assertNotNull($newConv);
        $this->assertNotNull($newConv['tipo_programa']);
        $this->assertEquals($programa->id, $newConv['tipo_programa']['id']);
    }
}
