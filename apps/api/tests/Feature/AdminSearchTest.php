<?php

namespace Tests\Feature;

use App\Models\Convocatoria;
use App\Models\Institucion;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\TipoPrograma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AdminSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private string $solicitanteToken;

    protected function setUp(): void
    {
        parent::setUp();

        Rol::updateOrCreate(['id' => 1], ['nombre' => 'Admin', 'slug' => 'admin']);
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);

        $admin = User::factory()->create(['rol_id' => 1]);
        $solicitante = User::factory()->create(['rol_id' => 4]);

        $this->adminToken = JWTAuth::fromUser($admin);
        $this->solicitanteToken = JWTAuth::fromUser($solicitante);
    }

    public function test_search_returns_empty_with_short_query(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/admin/search?q=a')
            ->assertOk()
            ->assertExactJson([
                'solicitudes' => [],
                'usuarios' => [],
                'instituciones' => [],
            ]);
    }

    public function test_search_matches_by_folio_titulo_email_y_nombre_institucion(): void
    {
        $tipoPrograma = TipoPrograma::factory()->create();
        $convocatoria = Convocatoria::factory()->create(['tipo_programa_id' => $tipoPrograma->id]);
        $institucion = Institucion::factory()->create(['nombre' => 'UAEMex Biotecnología']);
        $user = User::factory()->create([
            'rol_id' => 4,
            'email' => 'investigador@uaemex.mx',
            'institucion_id' => $institucion->id,
        ]);
        $solicitud = Solicitud::create([
            'folio' => 'COMECYT-2026-XYZQAB',
            'user_id' => $user->id,
            'institucion_id' => $institucion->id,
            'convocatoria_id' => $convocatoria->id,
            'titulo_proyecto' => 'Investigación de biotecnología avanzada',
            'descripcion_proyecto' => 'desc',
            'monto_solicitado' => 50000,
            'estado' => 'enviada',
        ]);

        // Match por folio
        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/admin/search?q=XYZQAB');
        $res->assertOk();
        $this->assertCount(1, $res->json('solicitudes'));
        $this->assertEquals($solicitud->id, $res->json('solicitudes.0.id'));

        // Match por título
        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/admin/search?q=biotecnolog');
        $res->assertOk();
        $this->assertCount(1, $res->json('solicitudes'));
        $this->assertCount(1, $res->json('instituciones'));

        // Match por email de usuario
        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/admin/search?q=uaemex.mx');
        $res->assertOk();
        $this->assertGreaterThanOrEqual(1, count($res->json('usuarios')));
    }

    public function test_search_requires_admin(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->solicitanteToken])
            ->getJson('/api/admin/search?q=algo')
            ->assertForbidden();
    }

    public function test_search_caps_results_per_category(): void
    {
        // 12 instituciones que matchean "Institucion Test"
        for ($i = 1; $i <= 12; $i++) {
            Institucion::factory()->create(['nombre' => "Institucion Test {$i}"]);
        }

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/admin/search?q=Institucion+Test');

        $res->assertOk();
        $this->assertLessThanOrEqual(8, count($res->json('instituciones')));
    }
}
