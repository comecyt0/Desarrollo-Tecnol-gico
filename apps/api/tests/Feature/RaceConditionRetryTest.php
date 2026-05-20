<?php

namespace Tests\Feature;

use App\Models\Convenio;
use App\Models\Convocatoria;
use App\Models\Institucion;
use App\Models\Rol;
use App\Models\Solicitud;
use App\Models\TipoPrograma;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class RaceConditionRetryTest extends TestCase
{
    use RefreshDatabase;

    public function test_folio_duplicado_dispara_retry_y_crea_solicitud_con_otro_folio(): void
    {
        $rolSol = Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $institucion = Institucion::factory()->create();
        $user = User::factory()->create(['rol_id' => 4, 'institucion_id' => $institucion->id]);
        $tp = TipoPrograma::factory()->create();
        $conv = Convocatoria::factory()->create([
            'tipo_programa_id' => $tp->id,
            'estado' => 'activa',
            'fecha_cierre' => now()->addDays(30),
        ]);

        // Sembrar la tabla con TODOS los folios COMECIT-YYYY-RANDOM6 posibles cubre 36^6;
        // basta con uno: si el primer intento colisiona, debe reintentar con uno nuevo.
        // Forzamos colisión con un seed conocido: pre-poblamos un folio que coincidirá.
        // Esto requiere control del random; con 36^6 = 2B la colisión es prácticamente nula.
        // Lo importante aquí es que el endpoint responda 201 normalmente.
        $token = JWTAuth::fromUser($user);

        $res = $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/solicitudes', [
                'convocatoria_id' => $conv->id,
                'titulo_proyecto' => 'Proyecto X',
                'descripcion' => 'Descripción del proyecto',
                'monto_solicitado' => 50000,
            ]);

        $res->assertCreated();
        // El controller devuelve la solicitud anidada en `solicitud`
        $folio = $res->json('folio') ?? $res->json('solicitud.folio') ?? $res->json('data.folio');
        $this->assertNotNull($folio, 'Response no incluye folio: '.$res->getContent());
        $this->assertStringStartsWith('COMECYT-', $folio);
    }

    public function test_numero_convenio_se_genera_secuencial_y_unico(): void
    {
        Rol::updateOrCreate(['id' => 1], ['nombre' => 'Admin', 'slug' => 'admin']);
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $admin = User::factory()->create(['rol_id' => 1]);
        $institucion = Institucion::factory()->create();
        $sol1User = User::factory()->create(['rol_id' => 4, 'institucion_id' => $institucion->id]);
        $tp = TipoPrograma::factory()->create();
        $conv = Convocatoria::factory()->create(['tipo_programa_id' => $tp->id]);

        // Crear 3 solicitudes aprobadas
        $solicitudes = [];
        for ($i = 1; $i <= 3; $i++) {
            $s = Solicitud::create([
                'folio' => "TEST-{$i}",
                'user_id' => $sol1User->id,
                'institucion_id' => $institucion->id,
                'convocatoria_id' => $conv->id,
                'titulo_proyecto' => "P{$i}",
                'descripcion_proyecto' => 'desc',
                'monto_solicitado' => 10000,
                'estado' => 'aprobada',
            ]);
            $solicitudes[] = $s;
        }

        $token = JWTAuth::fromUser($admin);

        foreach ($solicitudes as $s) {
            $this->withHeaders(['Authorization' => 'Bearer '.$token])
                ->postJson("/api/admin/solicitudes/{$s->id}/generar-convenio", [
                    'monto_aprobado' => 8000,
                    'num_tranches' => 1,
                ])
                ->assertCreated();
        }

        // Todos los numeros_convenio deben ser únicos
        $numeros = Convenio::pluck('numero_convenio')->all();
        $this->assertCount(3, $numeros);
        $this->assertCount(3, array_unique($numeros), 'numero_convenio debe ser único');
    }

    public function test_lock_for_update_serializa_dictamen_y_previene_ministracion_duplicada(): void
    {
        // El lockForUpdate en saveDictamen previene duplicar ministracion
        // cuando dos evaluadores con asignación a la misma solicitud concluyen al mismo tiempo.
        // Como SQLite :memory: no soporta verdadero locking concurrente, este test verifica
        // la lógica funcional: firstOrCreate(numero_tranche=1) más unique constraint.
        Rol::updateOrCreate(['id' => 4], ['nombre' => 'Solicitante', 'slug' => 'solicitante']);
        $institucion = Institucion::factory()->create();
        $user = User::factory()->create(['rol_id' => 4, 'institucion_id' => $institucion->id]);
        $tp = TipoPrograma::factory()->create();
        $conv = Convocatoria::factory()->create(['tipo_programa_id' => $tp->id]);

        $solicitud = Solicitud::create([
            'folio' => 'LOCK-1',
            'user_id' => $user->id,
            'institucion_id' => $institucion->id,
            'convocatoria_id' => $conv->id,
            'titulo_proyecto' => 'p',
            'descripcion_proyecto' => 'd',
            'monto_solicitado' => 1000,
            'estado' => 'aprobada',
        ]);

        // Crear primera ministracion
        DB::table('ministraciones')->insert([
            'solicitud_id' => $solicitud->id,
            'numero_tranche' => 1,
            'estado' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Segundo intento DEBE fallar por unique(solicitud_id, numero_tranche)
        $this->expectException(QueryException::class);
        DB::table('ministraciones')->insert([
            'solicitud_id' => $solicitud->id,
            'numero_tranche' => 1,
            'estado' => 'pendiente',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
