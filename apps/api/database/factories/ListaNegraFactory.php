<?php

namespace Database\Factories;

use App\Models\ListaNegra;
use App\Models\Institucion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ListaNegra>
 */
class ListaNegraFactory extends Factory
{
    protected $model = ListaNegra::class;

    public function definition(): array
    {
        return [
            'institucion_id'         => Institucion::factory(),
            'solicitud_id'           => null,
            'sancionado_por'         => null,
            'motivo'                 => fake()->sentence(),
            'fecha_inicio_sancion'   => now()->subMonth()->toDateString(),
            'fecha_fin_sancion'      => null,
            'activa'                 => true,
            'created_at'             => now(),
            'updated_at'             => now(),
        ];
    }

    public function inactiva(): static
    {
        return $this->state([
            'activa' => false,
        ]);
    }

    public function conFechaFin(): static
    {
        return $this->state([
            'fecha_fin_sancion' => now()->addYear()->toDateString(),
        ]);
    }
}
