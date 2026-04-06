<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
use App\Models\Institucion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UsuariosPruebaSeeder extends Seeder
{
    /**
     * Crear usuarios de prueba para cada rol
     * Este seeder crea:
     * - revisor@comecyt.gob.mx (Revisor Documental)
     * - evaluador@comecyt.gob.mx (Evaluador Técnico)
     * - solicitante@institucion.mx (Solicitante de institución)
     */
    public function run(): void
    {
        $comecyt = Institucion::where('acronimo', 'COMECYT')->first();
        $revisorRol = Rol::where('slug', 'revisor')->first();
        $evaluadorRol = Rol::where('slug', 'evaluador')->first();
        $solicitanteRol = Rol::where('slug', 'solicitante')->first();

        // Usuario Revisor (asd@asd.com - Compatible con sesiones anteriores)
        User::updateOrCreate(
            ['email' => 'asd@asd.com'],
            [
                'name' => 'Revisor COMECYT',
                'password' => Hash::make('password123'),
                'rol_id' => $revisorRol->id,
                'institucion_id' => $comecyt->id,
                'cargo' => 'Revisor Documental',
                'telefono' => '7221234568',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        // Usuario Evaluador Técnico
        $uaemex = Institucion::firstOrCreate(
            ['acronimo' => 'UAEMEX'],
            [
                'nombre' => 'Universidad Autónoma del Estado de México',
                'tipo' => 'publica',
                'estado' => 'Estado de México',
                'municipio' => 'Toluca',
                'activo' => true
            ]
        );

        User::updateOrCreate(
            ['email' => 'evaluadorr@uaemex.mx'],
            [
                'name' => 'Evaluador UAEMEX',
                'password' => Hash::make('password123'),
                'rol_id' => $evaluadorRol->id,
                'institucion_id' => $uaemex->id,
                'cargo' => 'Evaluador Técnico',
                'telefono' => '7221234569',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        // Usuario Solicitante de ejemplo
        User::updateOrCreate(
            ['email' => 'solicitante@institucion.mx'],
            [
                'name' => 'Investigador Ejemplo',
                'password' => Hash::make('password123'),
                'rol_id' => $solicitanteRol->id,
                'institucion_id' => $uaemex->id,
                'cargo' => 'Investigador',
                'telefono' => '7221234570',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        $this->command->info('Usuarios de prueba creados:');
        $this->command->info('  - Revisor: asd@asd.com / password123');
        $this->command->info('  - Evaluador: evaluadorr@uaemex.mx / password123');
        $this->command->info('  - Solicitante: solicitante@institucion.mx / password123');
    }
}
