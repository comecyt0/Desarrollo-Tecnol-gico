<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Rol;
use App\Models\Institucion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $roles = [
            ['nombre' => 'Administrador COMECYT', 'slug' => 'admin', 'descripcion' => 'Control total del sistema'],
            ['nombre' => 'Revisor COMECYT', 'slug' => 'revisor', 'descripcion' => 'Revisión documental de solicitudes'],
            ['nombre' => 'Evaluador Institucional', 'slug' => 'evaluador', 'descripcion' => 'Evaluador técnico/académico'],
            ['nombre' => 'Solicitante Institucional', 'slug' => 'solicitante', 'descripcion' => 'Investigador / Representante de institución'],
        ];

        foreach ($roles as $role) {
            Rol::updateOrCreate(['slug' => $role['slug']], $role);
        }

        // 2. Institución por defecto (COMECYT)
        $comecyt = Institucion::updateOrCreate(
            ['acronimo' => 'COMECYT'],
            [
                'nombre' => 'Consejo Mexiquense de Ciencia y Tecnología',
                'tipo' => 'publica',
                'estado' => 'Estado de México',
                'municipio' => 'Toluca',
                'activo' => true
            ]
        );

        // 3. Admin principal
        $adminRol = Rol::where('slug', 'admin')->first();
        
        User::updateOrCreate(
            ['email' => 'admin@comecyt.gob.mx'],
            [
                'name' => 'Super Administrador',
                'password' => Hash::make('password123'),
                'rol_id' => $adminRol->id,
                'institucion_id' => $comecyt->id,
                'cargo' => 'Director',
                'telefono' => '7221234567',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]
        );

        $this->command->info('Seeders básicos ejecutados: Roles, Institucion COMECYT y Usuario admin (admin@comecyt.gob.mx / password123)');

        // 4. Programa dinámicos (5 programas COMECYT)
        $this->call(TipoProgramaSeeder::class);
        $this->call(ProgramaEtapasSeeder::class);
        $this->call(ProgramaModalidadesSeeder::class);
        $this->call(ProgramaCriteriosSeeder::class);
        $this->call(ProgramaRubrosSeeder::class);

        $this->command->info('Programas dinámicos: PFPI, Prototipos, IPFE, Vinculación, Emprendedores - COMPLETADOS');
    }
}
