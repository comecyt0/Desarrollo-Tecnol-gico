<?php

namespace Database\Seeders;

use App\Models\CarouselSlide;
use Illuminate\Database\Seeder;

class CarouselSlideSeeder extends Seeder
{
    public function run(): void
    {
        CarouselSlide::truncate();

        // NOTA: En producción, el admin debe reemplazar estas imágenes desde
        // /admin/carrusel usando imágenes propias de COMECYT.
        // Se usan placeholders de picsum.photos (IDs fijos = imágenes estables).
        $slides = [
            [
                'titulo' => 'Gestión de Proyectos de Desarrollo Tecnológico',
                'subtitulo' => 'Plataforma Integral de Vinculación',
                'descripcion' => 'Administra el ciclo completo de tus proyectos de investigación y desarrollo tecnológico con transparencia y eficiencia.',
                'imagen_url' => 'https://picsum.photos/id/3/1400/800',
                'badge_texto' => 'COMECYT 2026',
                'orden' => 1,
                'activo' => true,
            ],
            [
                'titulo' => 'Vinculación Institucional y Científica',
                'subtitulo' => 'Conectando Instituciones con la Innovación',
                'descripcion' => 'Fortalece la colaboración entre universidades, centros de investigación y el sector productivo del Estado de México.',
                'imagen_url' => 'https://picsum.photos/id/20/1400/800',
                'badge_texto' => 'Red Científica',
                'orden' => 2,
                'activo' => true,
            ],
            [
                'titulo' => 'Evaluación Técnica Transparente',
                'subtitulo' => 'Dictámenes con Rigor Académico',
                'descripcion' => 'Sistema de evaluación por pares especialistas con criterios objetivos, garantizando la calidad de los proyectos apoyados.',
                'imagen_url' => 'https://picsum.photos/id/48/1400/800',
                'badge_texto' => 'Evaluación 2026',
                'orden' => 3,
                'activo' => true,
            ],
            [
                'titulo' => 'Fondos para la Innovación Mexiquense',
                'subtitulo' => 'Ministeraciones Ágiles y Seguras',
                'descripcion' => 'Gestión eficiente de recursos públicos para impulsar el desarrollo científico y tecnológico del Estado de México.',
                'imagen_url' => 'https://picsum.photos/id/96/1400/800',
                'badge_texto' => 'Apoyo Gubernamental',
                'orden' => 4,
                'activo' => true,
            ],
        ];

        foreach ($slides as $slide) {
            CarouselSlide::create($slide);
        }
    }
}
