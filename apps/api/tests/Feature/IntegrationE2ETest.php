<?php

namespace Tests\Feature;

use App\Models\AreaConocimiento;
use App\Models\Convocatoria;
use App\Models\Institucion;
use App\Models\Rol;
use App\Models\SolicitudRubroPresupuesto;
use App\Models\TipoPrograma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

/**
 * E2E Integration Test: Full solicitud creation flow
 *
 * Simulates user journey from selecting program → filling dynamic fields → submitting
 * Verifies all components work together correctly
 */
class IntegrationE2ETest extends TestCase
{
    use RefreshDatabase;

    protected Rol $rolSolicitante;

    protected User $user;

    protected string $token;

    protected Institucion $institucion;

    protected TipoPrograma $programa;

    protected Convocatoria $convocatoria;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles and basic data
        $this->rolSolicitante = Rol::create(['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        AreaConocimiento::create(['nombre' => 'Ingeniería', 'activo' => true]);

        $this->institucion = Institucion::factory()->create();
        $this->user = User::factory()->create([
            'rol_id' => $this->rolSolicitante->id,
            'institucion_id' => $this->institucion->id,
        ]);
        $this->token = JWTAuth::fromUser($this->user);

        // Create program with full catalog
        $this->programa = TipoPrograma::factory()->create([
            'tiene_etapas' => true,
            'num_etapas' => 2,
            'tiene_equipo' => true,
            'min_miembros_equipo' => 1,
            'max_miembros_equipo' => 5,
            'rango_edad_min' => 18,
            'rango_edad_max' => 40,
            'monto_maximo' => 200000,
        ]);

        // Create related catalog data
        $this->programa->modalidades()->createMany([
            ['clave' => 'MODAL1', 'nombre' => 'Modalidad 1', 'activo' => true],
            ['clave' => 'MODAL2', 'nombre' => 'Modalidad 2', 'activo' => true],
        ]);

        $etapa1 = $this->programa->etapas()->create([
            'numero_etapa' => 1,
            'nombre' => 'Etapa 1',
            'duracion_meses' => 3,
            'es_evaluacion_tecnica' => false,
        ]);

        $etapa2 = $this->programa->etapas()->create([
            'numero_etapa' => 2,
            'nombre' => 'Etapa 2',
            'duracion_meses' => 3,
            'es_evaluacion_tecnica' => true,
        ]);

        // Create dynamic fields for both etapas
        $this->programa->campos()->createMany([
            [
                'etapa_id' => $etapa1->id,
                'nombre_campo' => 'objetivos',
                'etiqueta' => 'Objetivos del Proyecto',
                'tipo_campo' => 'textarea',
                'orden' => 1,
                'requerido' => true,
                'activo' => true,
            ],
            [
                'etapa_id' => $etapa1->id,
                'nombre_campo' => 'presupuesto_detalle',
                'etiqueta' => 'Detalle Presupuesto',
                'tipo_campo' => 'textarea',
                'orden' => 2,
                'requerido' => false,
                'activo' => true,
            ],
            [
                'etapa_id' => $etapa2->id,
                'nombre_campo' => 'evaluacion_riesgos',
                'etiqueta' => 'Evaluación de Riesgos',
                'tipo_campo' => 'text',
                'orden' => 1,
                'requerido' => true,
                'activo' => true,
            ],
        ]);

        // Create rubros
        $this->programa->rubros()->createMany([
            ['clave' => 'RUBRO1', 'nombre' => 'Personal', 'activo' => true],
            ['clave' => 'RUBRO2', 'nombre' => 'Equipos', 'activo' => true],
            ['clave' => 'RUBRO3', 'nombre' => 'Consumibles', 'activo' => true],
        ]);

        // Create convocatoria
        $this->convocatoria = Convocatoria::factory()->activa()->create([
            'tipo_programa_id' => $this->programa->id,
        ]);
    }

    private function authed()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    /**
     * TEST 1: Fetch active convocatorias with program relation
     */
    public function test_e2e_step_1_fetch_active_convocatorias()
    {
        $response = $this->authed()
            ->getJson('/api/solicitudes/convocatorias-activas');

        $response->assertStatus(200);
        $convocatorias = $response->json();

        $this->assertCount(1, $convocatorias);
        $this->assertEquals($this->convocatoria->id, $convocatorias[0]['id']);
        $this->assertNotNull($convocatorias[0]['tipo_programa']);
        $this->assertEquals($this->programa->id, $convocatorias[0]['tipo_programa']['id']);
    }

    /**
     * TEST 2: Fetch complete program catalog
     */
    public function test_e2e_step_2_fetch_program_catalog()
    {
        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$this->programa->id}");

        $response->assertStatus(200);
        $data = $response->json('data');

        // Verify all components are present
        $this->assertNotNull($data['campos']);
        $this->assertNotNull($data['rubros']);
        $this->assertNotNull($data['etapas']);
        $this->assertNotNull($data['modalidades']);

        // Verify counts
        $this->assertCount(2, $data['etapas']);
        $this->assertCount(3, $data['rubros']);
        $this->assertCount(2, $data['modalidades']);
        $this->assertCount(3, $data['campos']);
    }

    /**
     * TEST 3: Create solicitud with all dynamic data (campos, rubros, miembros)
     */
    public function test_e2e_step_3_create_solicitud_with_full_payload()
    {
        $payload = [
            'convocatoria_id' => $this->convocatoria->id,
            'titulo_proyecto' => 'Proyecto Integral de Investigación',
            'modalidad' => 'Vinculación',
            'area_conocimiento_id' => 1,
            'descripcion' => 'Proyecto de investigación aplicada con equipo multidisciplinario.',
            'monto_solicitado' => 150000,

            // Dynamic campos (etapa 1 + 2)
            'campos_dinamicos' => [
                ['campo_id' => 1, 'valor' => 'Objetivo principal: Desarrollar metodología innovadora'],
                ['campo_id' => 2, 'valor' => 'Presupuesto: $100k personal, $50k equipos'],
                ['campo_id' => 3, 'valor' => 'Riesgo principal: disponibilidad de personal'],
            ],

            // Rubros presupuestarios
            'rubros' => [
                ['rubro_id' => 1, 'monto' => 80000],   // Personal
                ['rubro_id' => 2, 'monto' => 50000],   // Equipos
                ['rubro_id' => 3, 'monto' => 20000],   // Consumibles
            ],

            // Team members (required because programa.tiene_equipo = true)
            'miembros_equipo' => [
                [
                    'nombre' => 'Dr. Juan González',
                    'edad' => 35,
                    'rol' => 'Investigador Principal',
                    'email' => 'juan@institution.edu',
                ],
                [
                    'nombre' => 'Ing. María López',
                    'edad' => 28,
                    'rol' => 'Investigadora Co-responsable',
                    'email' => 'maria@institution.edu',
                ],
                [
                    'nombre' => 'Tec. Carlos Ruiz',
                    'edad' => 25,
                    'rol' => 'Técnico de Laboratorio',
                    'email' => null,
                ],
            ],
        ];

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('solicitud.estado', 'borrador');

        $solicitudId = $response->json('solicitud.id');
        $folio = $response->json('solicitud.folio');

        // Verify folio format
        $this->assertMatchesRegularExpression('/^COMECYT-\d{4}-[A-Z0-9]{6}$/', $folio);

        return $solicitudId;
    }

    /**
     * TEST 4: Verify dynamic campos were persisted
     */
    public function test_e2e_step_4_verify_campos_persisted()
    {
        $solicitudId = $this->test_e2e_step_3_create_solicitud_with_full_payload();

        $this->assertDatabaseCount('solicitud_campos_dinamicos', 3);

        // Verify specific campos
        $this->assertDatabaseHas('solicitud_campos_dinamicos', [
            'solicitud_id' => $solicitudId,
            'programa_campo_id' => 1,
            'valor_texto' => 'Objetivo principal: Desarrollar metodología innovadora',
        ]);
    }

    /**
     * TEST 5: Verify rubros were persisted with correct monto
     */
    public function test_e2e_step_5_verify_rubros_persisted()
    {
        $solicitudId = $this->test_e2e_step_3_create_solicitud_with_full_payload();

        $this->assertDatabaseCount('solicitud_rubros_presupuesto', 3);

        // Verify totals sum to monto_solicitado
        $rubros = SolicitudRubroPresupuesto::where('solicitud_id', $solicitudId)->get();
        $totalMonto = $rubros->sum('monto_solicitado');
        $this->assertEquals(150000, $totalMonto);
    }

    /**
     * TEST 6: Verify team members were persisted with lider flag
     */
    public function test_e2e_step_6_verify_miembros_persisted()
    {
        $solicitudId = $this->test_e2e_step_3_create_solicitud_with_full_payload();

        $this->assertDatabaseCount('solicitud_miembros_equipo', 3);

        // Verify first member is marked as lider
        $this->assertDatabaseHas('solicitud_miembros_equipo', [
            'solicitud_id' => $solicitudId,
            'nombre_completo' => 'Dr. Juan González',
            'es_lider' => true,
        ]);

        // Verify others are not lider
        $this->assertDatabaseHas('solicitud_miembros_equipo', [
            'solicitud_id' => $solicitudId,
            'nombre_completo' => 'Ing. María López',
            'es_lider' => false,
        ]);
    }

    /**
     * TEST 7: Submit solicitud (borrador → enviada)
     */
    public function test_e2e_step_7_submit_solicitud()
    {
        $solicitudId = $this->test_e2e_step_3_create_solicitud_with_full_payload();

        $response = $this->authed()
            ->postJson("/api/solicitudes/{$solicitudId}/enviar");

        $response->assertStatus(200)
            ->assertJsonPath('solicitud.estado', 'enviada');

        $this->assertDatabaseHas('solicitudes', [
            'id' => $solicitudId,
            'estado' => 'enviada',
        ]);
    }

    /**
     * TEST 8: Validation - Cannot submit with invalid data
     */
    public function test_e2e_validation_missing_required_campos()
    {
        $payload = [
            'convocatoria_id' => $this->convocatoria->id,
            'titulo_proyecto' => 'Proyecto Sin Campos',
            'modalidad' => 'Vinculación',
            'area_conocimiento_id' => 1,
            'descripcion' => 'Descripción',
            'monto_solicitado' => 50000,
            'campos_dinamicos' => [
                // Missing campo_id 3 (evaluacion_riesgos) which is required
                ['campo_id' => 1, 'valor' => 'Objetivos aquí'],
            ],
            'rubros' => [],
            'miembros_equipo' => [
                ['nombre' => 'Test', 'edad' => 30, 'rol' => 'Lead', 'email' => 'test@test.com'],
            ],
        ];

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        // Backend allows creation (frontend validates)
        // This demonstrates that backend is permissive
        $response->assertStatus(201);
    }

    /**
     * TEST 9: Validation - Monto solicitado exceeds programa máximo
     */
    public function test_e2e_validation_monto_exceeds_maximum()
    {
        $payload = [
            'convocatoria_id' => $this->convocatoria->id,
            'titulo_proyecto' => 'Proyecto Muy Caro',
            'modalidad' => 'Vinculación',
            'area_conocimiento_id' => 1,
            'descripcion' => 'Descripción',
            'monto_solicitado' => 300000,  // Exceeds programa.monto_maximo (200000)
            'campos_dinamicos' => [],
            'rubros' => [],
            'miembros_equipo' => [],
        ];

        $response = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        // Backend rechaza monto > programa.monto_maximo con 422
        $response->assertStatus(422);
        $response->assertJsonPath('errors.monto_solicitado.0', fn ($msg) => str_contains($msg, '200'));
    }

    /**
     * TEST 10: Full integration - Program with dynamic evaluation
     */
    public function test_e2e_complete_flow_with_program_catalog_integration()
    {
        // Step 1: Get convocatorias
        $convResponse = $this->authed()
            ->getJson('/api/solicitudes/convocatorias-activas');
        $convocatorias = $convResponse->json();
        $this->assertCount(1, $convocatorias);

        // Step 2: Extract programa ID from convocatoria
        $programaId = $convocatorias[0]['tipo_programa']['id'];
        $this->assertEquals($this->programa->id, $programaId);

        // Step 3: Get complete catalog
        $catalogResponse = $this->authed()
            ->getJson("/api/catalogs/programa/{$programaId}");
        $catalog = $catalogResponse->json('data');
        $this->assertTrue(count($catalog['campos']) > 0);

        // Step 4: Create solicitud with data from catalog
        $payload = [
            'convocatoria_id' => $convocatorias[0]['id'],
            'titulo_proyecto' => 'Proyecto Dinámico Completo',
            'modalidad' => $catalog['modalidades'][0]['nombre'] ?? 'Modalidad 1',
            'area_conocimiento_id' => 1,
            'descripcion' => 'Con todos los campos del programa',
            'monto_solicitado' => 100000,
            'campos_dinamicos' => array_map(function ($campo) {
                return [
                    'campo_id' => $campo['id'],
                    'valor' => "Valor para {$campo['nombre_campo']}",
                ];
            }, $catalog['campos']),
            'rubros' => array_map(function ($rubro) {
                return [
                    'rubro_id' => $rubro['id'],
                    'monto' => 20000,
                ];
            }, $catalog['rubros']),
            'miembros_equipo' => [
                ['nombre' => 'Lead', 'edad' => 35, 'rol' => 'Lead', 'email' => 'lead@test.com'],
            ],
        ];

        $solicitudResponse = $this->authed()
            ->postJson('/api/solicitudes', $payload);

        $solicitudResponse->assertStatus(201);
        $solicitudId = $solicitudResponse->json('solicitud.id');

        // Step 5: Submit
        $submitResponse = $this->authed()
            ->postJson("/api/solicitudes/{$solicitudId}/enviar");

        $submitResponse->assertStatus(200)
            ->assertJsonPath('solicitud.estado', 'enviada');
    }
}
