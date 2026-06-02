<?php

namespace Tests\Feature;

use App\Models\AreaConocimiento;
use App\Models\Convocatoria;
use App\Models\Empresa;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class SolicitudFlowTest extends TestCase
{
    use RefreshDatabase;

    protected Rol $rolAdmin;

    protected Rol $rolRevisor;

    protected Rol $rolEvaluador;

    protected Rol $rolSolicitante;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles and store as class properties
        $this->rolAdmin = Rol::create(['nombre' => 'Administrador', 'slug' => 'admin']);
        $this->rolRevisor = Rol::create(['nombre' => 'Revisor', 'slug' => 'revisor']);
        $this->rolEvaluador = Rol::create(['nombre' => 'Evaluador', 'slug' => 'evaluador']);
        $this->rolSolicitante = Rol::create(['nombre' => 'Solicitante', 'slug' => 'solicitante']);
    }

    /**
     * Test the full solicitation flow from draft to revision
     */
    public function test_solicitation_creation_and_submission_flow()
    {
        // 1. Setup - Create basic institutions and areas
        $empresa = Empresa::create([
            'nombre' => 'Tecnológico de Monterrey',
            'acronimo' => 'ITESM',
            'tipo' => 'privada',
        ]);

        AreaConocimiento::create(['nombre' => 'Ciencias de la Tierra', 'activo' => true]);

        // 2. Create an Active Convocatoria
        $convocatoria = Convocatoria::create([
            'nombre' => 'Beca Test 2026',
            'ejercicio_fiscal' => '2026',
            'descripcion' => 'Prueba',
            'fecha_apertura' => now()->subDay(),
            'fecha_cierre' => now()->addMonth(),
            'estado' => 'activa',
        ]);

        // 3. Create a Solicitante User
        $user = User::factory()->create([
            'rol_id' => $this->rolSolicitante->id,
            'empresa_id' => $empresa->id,
        ]);

        $token = JWTAuth::fromUser($user);

        // 4. Fetch active convocatorias
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/solicitudes/convocatorias-activas');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json());
        $convocatoriaId = $response->json()[0]['id'];

        // 5. Create a Draft Solicitud
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/solicitudes', [
                'convocatoria_id' => $convocatoriaId,
                'titulo_proyecto' => 'Proyecto de Prueba de Calidad',
                'modalidad' => 'Vinculación',
                'area_conocimiento_id' => 1,
                'descripcion' => 'Este es un resumen de prueba para el test de flujo.',
                'monto_solicitado' => 50000,
            ]);

        $response->assertStatus(201);
        $solicitudId = $response->json()['solicitud']['id'];

        // 6. Submit the Solicitud
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/api/solicitudes/{$solicitudId}/enviar");

        $response->assertStatus(200);
        $this->assertEquals('enviada', $response->json()['solicitud']['estado']);

        // 7. Review by Revisor
        $revisor = User::factory()->create(['rol_id' => $this->rolRevisor->id]);
        $revisorToken = JWTAuth::fromUser($revisor);

        $response = $this->withHeader('Authorization', 'Bearer '.$revisorToken)
            ->postJson("/api/revisor/solicitudes/{$solicitudId}/aprobar");

        $response->assertStatus(200);
        $this->assertEquals('en_evaluacion', $response->json()['solicitud']['estado']);
    }
}
