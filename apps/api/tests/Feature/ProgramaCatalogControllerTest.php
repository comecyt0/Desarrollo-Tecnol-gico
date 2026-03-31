<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\TipoPrograma;
use App\Models\ProgramaCampo;
use App\Models\ProgramaDocumento;
use App\Models\ProgramaCriterioEvaluacion;
use App\Models\ProgramaRubro;
use App\Models\ProgramaEtapa;
use App\Models\ProgramaModalidad;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgramaCatalogControllerTest extends RefreshDatabase
{
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // ===== CAMPOS TESTS =====

    public function test_get_campos_returns_fields_for_program()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCampo::factory()->count(3), 'campos')
            ->create(['tiene_etapas' => false]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'programa' => ['id', 'clave', 'nombre'],
                    'campos' => [
                        '*' => [
                            'id', 'nombre_campo', 'etiqueta', 'tipo_campo',
                            'orden', 'requerido', 'activo'
                        ]
                    ],
                    'etapas'
                ],
                'message'
            ])
            ->assertJsonCount(3, 'data.campos');
    }

    public function test_get_campos_excludes_inactive_fields()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => false]);

        ProgramaCampo::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true
        ]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertJsonCount(2, 'data.campos');
    }

    public function test_get_campos_orders_by_orden_field()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => false]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'nombre_campo' => 'field_3',
            'orden' => 3
        ]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'nombre_campo' => 'field_1',
            'orden' => 1
        ]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'nombre_campo' => 'field_2',
            'orden' => 2
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $campos = $response->json('data.campos');
        $this->assertEquals('field_1', $campos[0]['nombre_campo']);
        $this->assertEquals('field_2', $campos[1]['nombre_campo']);
        $this->assertEquals('field_3', $campos[2]['nombre_campo']);
    }

    public function test_get_campos_includes_etapas_when_multiestage()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => true, 'num_etapas' => 2]);

        ProgramaEtapa::factory()->count(2)->create(['tipo_programa_id' => $programa->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertJsonCount(2, 'data.etapas');
    }

    // ===== DOCUMENTOS TESTS =====

    public function test_get_documentos_returns_required_documents()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaDocumento::factory()->count(2), 'documentos')
            ->create(['tiene_etapas' => false]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/documentos");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'programa',
                    'documentos' => [
                        '*' => [
                            'id', 'nombre', 'descripcion', 'formato_permitido',
                            'tamaño_maximo_mb', 'obligatorio', 'orden'
                        ]
                    ],
                    'etapas'
                ]
            ])
            ->assertJsonCount(2, 'data.documentos');
    }

    public function test_get_documentos_excludes_inactive()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => false]);

        ProgramaDocumento::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true
        ]);

        ProgramaDocumento::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/documentos");

        $response->assertJsonCount(2, 'data.documentos');
    }

    // ===== CRITERIOS TESTS =====

    public function test_get_criterios_returns_evaluation_criteria()
    {
        $programa = TipoPrograma::factory()
            ->has(
                ProgramaCriterioEvaluacion::factory()->count(4),
                'criterios'
            )
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'programa',
                    'criterios' => [
                        '*' => [
                            'id', 'nombre', 'descripcion', 'ponderacion',
                            'puntaje_maximo', 'orden'
                        ]
                    ],
                    'suma_ponderaciones',
                    'num_criterios'
                ]
            ])
            ->assertJsonCount(4, 'data.criterios');
    }

    public function test_get_criterios_sums_ponderaciones_correctly()
    {
        $programa = TipoPrograma::factory()->create();

        ProgramaCriterioEvaluacion::factory()->create([
            'tipo_programa_id' => $programa->id,
            'ponderacion' => 25.00
        ]);

        ProgramaCriterioEvaluacion::factory()->create([
            'tipo_programa_id' => $programa->id,
            'ponderacion' => 25.00
        ]);

        ProgramaCriterioEvaluacion::factory()->create([
            'tipo_programa_id' => $programa->id,
            'ponderacion' => 50.00
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $response->assertJsonPath('data.suma_ponderaciones', 100.00);
        $response->assertJsonPath('data.num_criterios', 3);
    }

    public function test_get_criterios_groups_by_etapas()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => true]);

        $etapa1 = ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 1
        ]);

        $etapa2 = ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 2
        ]);

        ProgramaCriterioEvaluacion::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'etapa_id' => $etapa1->id
        ]);

        ProgramaCriterioEvaluacion::factory()->count(3)->create([
            'tipo_programa_id' => $programa->id,
            'etapa_id' => $etapa2->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $this->assertCount(2, $response->json('data.etapas'));
        $this->assertCount(2, $response->json('data.etapas.0.criterios_en_etapa'));
        $this->assertCount(3, $response->json('data.etapas.1.criterios_en_etapa'));
    }

    // ===== RUBROS TESTS =====

    public function test_get_rubros_returns_budget_items()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaRubro::factory()->count(3), 'rubros')
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/rubros");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'programa' => ['id', 'clave', 'nombre', 'monto_maximo'],
                    'rubros' => [
                        '*' => [
                            'id', 'clave', 'nombre', 'descripcion',
                            'porcentaje_maximo'
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(3, 'data.rubros');
    }

    public function test_get_rubros_excludes_inactive()
    {
        $programa = TipoPrograma::factory()->create();

        ProgramaRubro::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true
        ]);

        ProgramaRubro::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/rubros");

        $response->assertJsonCount(2, 'data.rubros');
    }

    // ===== ETAPAS TESTS =====

    public function test_get_etapas_returns_program_stages()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => true, 'num_etapas' => 3]);

        ProgramaEtapa::factory()->count(3)->create(['tipo_programa_id' => $programa->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/etapas");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'programa',
                    'etapas' => [
                        '*' => [
                            'id', 'numero_etapa', 'nombre', 'descripcion',
                            'duracion_meses', 'es_evaluacion_tecnica', 'puntaje_minimo'
                        ]
                    ],
                    'tiene_etapas',
                    'num_etapas'
                ]
            ])
            ->assertJsonCount(3, 'data.etapas');
    }

    public function test_get_etapas_orders_by_numero_etapa()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => true]);

        ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 3,
            'nombre' => 'Etapa 3'
        ]);

        ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 1,
            'nombre' => 'Etapa 1'
        ]);

        ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 2,
            'nombre' => 'Etapa 2'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/etapas");

        $etapas = $response->json('data.etapas');
        $this->assertEquals('Etapa 1', $etapas[0]['nombre']);
        $this->assertEquals('Etapa 2', $etapas[1]['nombre']);
        $this->assertEquals('Etapa 3', $etapas[2]['nombre']);
    }

    // ===== MODALIDADES TESTS =====

    public function test_get_modalidades_returns_program_modalities()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaModalidad::factory()->count(3), 'modalidades')
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/modalidades");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'programa',
                    'modalidades' => [
                        '*' => [
                            'id', 'clave', 'nombre', 'descripcion',
                            'monto_maximo_especifico'
                        ]
                    ]
                ]
            ])
            ->assertJsonCount(3, 'data.modalidades');
    }

    public function test_get_modalidades_excludes_inactive()
    {
        $programa = TipoPrograma::factory()->create();

        ProgramaModalidad::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true
        ]);

        ProgramaModalidad::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/modalidades");

        $response->assertJsonCount(2, 'data.modalidades');
    }

    // ===== SHOW (COMPLETE) TESTS =====

    public function test_show_returns_complete_program_configuration()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCampo::factory()->count(2), 'campos')
            ->has(ProgramaDocumento::factory()->count(2), 'documentos')
            ->has(ProgramaCriterioEvaluacion::factory()->count(2), 'criterios')
            ->has(ProgramaRubro::factory()->count(2), 'rubros')
            ->has(ProgramaEtapa::factory()->count(2), 'etapas')
            ->has(ProgramaModalidad::factory()->count(2), 'modalidades')
            ->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'programa',
                    'campos',
                    'documentos',
                    'criterios',
                    'rubros',
                    'etapas',
                    'modalidades'
                ]
            ])
            ->assertJsonCount(2, 'data.campos')
            ->assertJsonCount(2, 'data.documentos')
            ->assertJsonCount(2, 'data.criterios')
            ->assertJsonCount(2, 'data.rubros')
            ->assertJsonCount(2, 'data.etapas')
            ->assertJsonCount(2, 'data.modalidades');
    }

    // ===== ERROR TESTS =====

    public function test_returns_404_for_nonexistent_programa()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/catalogs/programa/9999/campos');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_campos()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_documentos()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/documentos");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_criterios()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_show()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}");

        $response->assertStatus(401);
    }

    // ===== CACHING TESTS =====

    public function test_campos_response_is_cached()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCampo::factory()->count(1), 'campos')
            ->create(['tiene_etapas' => false]);

        // First request (miss)
        $response1 = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        // Add new field
        ProgramaCampo::factory()->create(['tipo_programa_id' => $programa->id]);

        // Second request (hit) - should return cached data with 1 field, not 2
        $response2 = $this->actingAs($this->user)
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $this->assertCount(1, $response2->json('data.campos'));
    }
}
