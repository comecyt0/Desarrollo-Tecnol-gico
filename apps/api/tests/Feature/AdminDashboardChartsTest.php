<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Convocatoria;
use App\Models\Empresa;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\TipoPrograma;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class AdminDashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;

    private string $solicitanteToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Roles
        Rol::updateOrCreate(['id' => 1], ['nombre' => 'Admin', 'slug' => 'admin']);
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);

        $admin = User::factory()->create(['rol_id' => 1]);
        $solicitante = User::factory()->create(['rol_id' => 4]);

        $this->adminToken = JWTAuth::fromUser($admin);
        $this->solicitanteToken = JWTAuth::fromUser($solicitante);
    }

    public function test_admin_charts_returns_12_months_series_with_zeros_when_no_data(): void
    {
        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/admin/charts');

        $res->assertOk()
            ->assertJsonStructure([
                'series_mensual' => [
                    '*' => ['label', 'ym', 'solicitudes', 'monto_solicitado', 'monto_aprobado'],
                ],
                'distribucion_estado',
            ])
            ->assertJsonCount(12, 'series_mensual');
    }

    public function test_admin_charts_aggregates_solicitudes_and_montos_correctly(): void
    {
        $tipoPrograma = TipoPrograma::factory()->create();
        $convocatoria = Convocatoria::factory()->create(['tipo_programa_id' => $tipoPrograma->id]);
        $empresa = Empresa::factory()->create();
        $user = User::factory()->create([
            'rol_id' => 4,
            'empresa_id' => $empresa->id,
        ]);

        // 2 solicitudes este mes, una aprobada con convenio
        $s1 = Solicitud::create([
            'folio' => 'TEST-A',
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'convocatoria_id' => $convocatoria->id,
            'titulo_proyecto' => 'A',
            'descripcion_proyecto' => 'desc',
            'monto_solicitado' => 50000,
            'estado' => 'aprobada',
        ]);
        Solicitud::create([
            'folio' => 'TEST-B',
            'user_id' => $user->id,
            'empresa_id' => $empresa->id,
            'convocatoria_id' => $convocatoria->id,
            'titulo_proyecto' => 'B',
            'descripcion_proyecto' => 'desc',
            'monto_solicitado' => 30000,
            'estado' => 'borrador',
        ]);
        Convenio::create([
            'solicitud_id' => $s1->id,
            'numero_convenio' => 'COMECYT-'.date('Y').'-TEST',
            'estado' => 'firmado',
            'monto_aprobado' => 45000,
            'num_tranches' => 1,
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$this->adminToken])
            ->getJson('/api/admin/charts');

        $res->assertOk();
        $payload = $res->json();

        // El último mes (índice 11) debe tener 2 solicitudes y 80000 solicitado, 45000 aprobado
        $thisMonth = $payload['series_mensual'][11];
        $this->assertSame(2, $thisMonth['solicitudes']);
        $this->assertEquals(80000, $thisMonth['monto_solicitado']);
        $this->assertEquals(45000, $thisMonth['monto_aprobado']);

        // distribucion debe incluir 'aprobada' y 'borrador'
        $estados = collect($payload['distribucion_estado'])->pluck('estado')->all();
        $this->assertContains('aprobada', $estados);
        $this->assertContains('borrador', $estados);
    }

    public function test_admin_charts_requires_admin_role(): void
    {
        $this->withHeaders(['Authorization' => 'Bearer '.$this->solicitanteToken])
            ->getJson('/api/admin/charts')
            ->assertForbidden();
    }

    public function test_admin_charts_requires_auth(): void
    {
        $this->getJson('/api/admin/charts')->assertUnauthorized();
    }
}
