<?php

namespace Tests\Feature;

use App\Models\ProgramaCampo;
use App\Models\ProgramaCriterioEvaluacion;
use App\Models\ProgramaDocumento;
use App\Models\ProgramaEtapa;
use App\Models\ProgramaModalidad;
use App\Models\ProgramaRubro;
use App\Models\TipoPrograma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ProgramaCatalogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->token = JWTAuth::fromUser($this->user);
    }

    private function authed()
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    }

    // ===== CAMPOS TESTS =====

    public function test_get_campos_returns_fields_for_program()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCampo::factory()->count(3), 'campos')
            ->create(['tiene_etapas' => false]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('count', 3);
    }

    public function test_get_campos_excludes_inactive_fields()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => false]);

        ProgramaCampo::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true,
        ]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false,
        ]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertJsonCount(2, 'data');
    }

    public function test_get_campos_orders_by_orden_field()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => false]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'nombre_campo' => 'field_3',
            'orden' => 3,
        ]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'nombre_campo' => 'field_1',
            'orden' => 1,
        ]);

        ProgramaCampo::factory()->create([
            'tipo_programa_id' => $programa->id,
            'nombre_campo' => 'field_2',
            'orden' => 2,
        ]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $campos = $response->json('data');
        $this->assertEquals('field_1', $campos[0]['nombre_campo']);
        $this->assertEquals('field_2', $campos[1]['nombre_campo']);
        $this->assertEquals('field_3', $campos[2]['nombre_campo']);
    }

    // ===== DOCUMENTOS TESTS =====

    public function test_get_documentos_returns_required_documents()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaDocumento::factory()->count(2), 'documentos')
            ->create(['tiene_etapas' => false]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/documentos");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('count', 2);
    }

    public function test_get_documentos_excludes_inactive()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => false]);

        ProgramaDocumento::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true,
        ]);

        ProgramaDocumento::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false,
        ]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/documentos");

        $response->assertJsonCount(2, 'data');
    }

    public function test_documentos_returns_404_for_nonexistent_programa()
    {
        $response = $this->authed()
            ->getJson('/api/catalogs/programa/9999/documentos');

        $response->assertStatus(404);
    }

    // ===== CRITERIOS TESTS =====

    public function test_get_criterios_returns_flat_list_when_no_etapas()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCriterioEvaluacion::factory()->count(4), 'criterios')
            ->create(['tiene_etapas' => false]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(4, 'data');

        // Verify data is flat array, not grouped
        $this->assertIsArray($response->json('data'));
    }

    public function test_get_criterios_groups_by_etapas()
    {
        // Clear cache to prevent contamination from other tests
        Cache::flush();

        $programa = TipoPrograma::factory()->create(['tiene_etapas' => true]);

        $etapa1 = ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 1,
        ]);

        $etapa2 = ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 2,
        ]);

        ProgramaCriterioEvaluacion::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'etapa_id' => $etapa1->id,
        ]);

        ProgramaCriterioEvaluacion::factory()->count(3)->create([
            'tipo_programa_id' => $programa->id,
            'etapa_id' => $etapa2->id,
        ]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $data = $response->json('data');
        $this->assertCount(2, $data);

        // Buscar grupos por ID de etapa (no por índice — el orden puede variar)
        $group1 = collect($data)->first(fn ($g) => $g['etapa']['id'] === $etapa1->id);
        $group2 = collect($data)->first(fn ($g) => $g['etapa']['id'] === $etapa2->id);

        $this->assertNotNull($group1, 'Grupo de etapa 1 no encontrado en la respuesta');
        $this->assertNotNull($group2, 'Grupo de etapa 2 no encontrado en la respuesta');
        $this->assertCount(2, $group1['criterios']);
        $this->assertCount(3, $group2['criterios']);
    }

    public function test_criterios_returns_404_for_nonexistent_programa()
    {
        $response = $this->authed()
            ->getJson('/api/catalogs/programa/9999/criterios');

        $response->assertStatus(404);
    }

    // ===== RUBROS TESTS =====

    public function test_get_rubros_returns_budget_items()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaRubro::factory()->count(3), 'rubros')
            ->create();

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/rubros");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('count', 3);
    }

    public function test_get_rubros_excludes_inactive()
    {
        $programa = TipoPrograma::factory()->create();

        ProgramaRubro::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true,
        ]);

        ProgramaRubro::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false,
        ]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/rubros");

        $response->assertJsonCount(2, 'data');
    }

    public function test_rubros_returns_404_for_nonexistent_programa()
    {
        $response = $this->authed()
            ->getJson('/api/catalogs/programa/9999/rubros');

        $response->assertStatus(404);
    }

    // ===== ETAPAS TESTS =====

    public function test_get_etapas_returns_program_stages()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => true, 'num_etapas' => 3]);

        ProgramaEtapa::factory()->count(3)->create(['tipo_programa_id' => $programa->id]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/etapas");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('count', 3);
    }

    public function test_get_etapas_orders_by_numero_etapa()
    {
        $programa = TipoPrograma::factory()->create(['tiene_etapas' => true]);

        ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 3,
            'nombre' => 'Etapa 3',
        ]);

        ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 1,
            'nombre' => 'Etapa 1',
        ]);

        ProgramaEtapa::factory()->create([
            'tipo_programa_id' => $programa->id,
            'numero_etapa' => 2,
            'nombre' => 'Etapa 2',
        ]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/etapas");

        $etapas = $response->json('data');
        $this->assertEquals('Etapa 1', $etapas[0]['nombre']);
        $this->assertEquals('Etapa 2', $etapas[1]['nombre']);
        $this->assertEquals('Etapa 3', $etapas[2]['nombre']);
    }

    public function test_etapas_returns_404_for_nonexistent_programa()
    {
        $response = $this->authed()
            ->getJson('/api/catalogs/programa/9999/etapas');

        $response->assertStatus(404);
    }

    // ===== MODALIDADES TESTS =====

    public function test_get_modalidades_returns_program_modalities()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaModalidad::factory()->count(3), 'modalidades')
            ->create();

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/modalidades");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('count', 3);
    }

    public function test_get_modalidades_excludes_inactive()
    {
        $programa = TipoPrograma::factory()->create();

        ProgramaModalidad::factory()->count(2)->create([
            'tipo_programa_id' => $programa->id,
            'activo' => true,
        ]);

        ProgramaModalidad::factory()->create([
            'tipo_programa_id' => $programa->id,
            'activo' => false,
        ]);

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/modalidades");

        $response->assertJsonCount(2, 'data');
    }

    public function test_modalidades_returns_404_for_nonexistent_programa()
    {
        $response = $this->authed()
            ->getJson('/api/catalogs/programa/9999/modalidades');

        $response->assertStatus(404);
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

        $response = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'OK')
            ->assertJsonCount(2, 'data.campos')
            ->assertJsonCount(2, 'data.documentos')
            ->assertJsonCount(2, 'data.criterios')
            ->assertJsonCount(2, 'data.rubros')
            ->assertJsonCount(2, 'data.etapas')
            ->assertJsonCount(2, 'data.modalidades');
    }

    public function test_show_returns_404_for_nonexistent_programa()
    {
        $response = $this->authed()
            ->getJson('/api/catalogs/programa/9999');

        $response->assertStatus(404);
    }

    // ===== PUBLIC ACCESS TESTS =====
    // Estas rutas son públicas (no requieren auth) — el solicitante las usa antes de login

    public function test_unauthenticated_user_can_access_campos()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_can_access_documentos()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/documentos");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_can_access_criterios()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/criterios");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_can_access_rubros()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/rubros");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_can_access_etapas()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/etapas");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_can_access_modalidades()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}/modalidades");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_can_access_show()
    {
        $programa = TipoPrograma::factory()->create();

        $response = $this->getJson("/api/catalogs/programa/{$programa->id}");

        $response->assertStatus(200);
    }

    // ===== CACHING TESTS =====

    public function test_campos_response_is_cached()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCampo::factory()->count(1), 'campos')
            ->create(['tiene_etapas' => false]);

        // First request (miss)
        $response1 = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        // Add new field
        ProgramaCampo::factory()->create(['tipo_programa_id' => $programa->id]);

        // Second request (hit) - should return cached data with 1 field, not 2
        $response2 = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $this->assertCount(1, $response2->json('data'));
    }

    public function test_clear_cache_invalidates_all_cached_keys()
    {
        $programa = TipoPrograma::factory()
            ->has(ProgramaCampo::factory()->count(1), 'campos')
            ->create(['tiene_etapas' => false]);

        // First request - populate cache
        $this->authed()->getJson("/api/catalogs/programa/{$programa->id}/campos");

        // Add new field
        ProgramaCampo::factory()->create(['tipo_programa_id' => $programa->id]);

        // Clear cache
        $response = $this->authed()
            ->deleteJson("/api/catalogs/programa/{$programa->id}/cache");

        $response->assertStatus(200)
            ->assertJsonPath('programa_id', $programa->id);

        // Next request should see the new field
        $response2 = $this->authed()
            ->getJson("/api/catalogs/programa/{$programa->id}/campos");

        $this->assertCount(2, $response2->json('data'));
    }
}
