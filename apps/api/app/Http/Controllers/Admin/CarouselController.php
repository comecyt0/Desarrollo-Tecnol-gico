<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarouselSlide;
use Illuminate\Http\Request;

class CarouselController extends Controller
{
    /** Public: list active slides ordered */
    public function publicIndex()
    {
        $slides = CarouselSlide::where('activo', true)
            ->orderBy('orden')
            ->get();

        return response()->json($slides);
    }

    /** Admin: list all slides */
    public function index()
    {
        return response()->json(CarouselSlide::orderBy('orden')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:150',
            'subtitulo' => 'nullable|string|max:200',
            'descripcion' => 'nullable|string',
            'imagen_url' => 'nullable|string|max:500',
            'badge_texto' => 'nullable|string|max:100',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);

        $slide = CarouselSlide::create($validated);

        return response()->json(['message' => 'Slide creado.', 'slide' => $slide], 201);
    }

    public function update(Request $request, CarouselSlide $carouselSlide)
    {
        $validated = $request->validate([
            'titulo' => 'string|max:150',
            'subtitulo' => 'nullable|string|max:200',
            'descripcion' => 'nullable|string',
            'imagen_url' => 'nullable|string|max:500',
            'badge_texto' => 'nullable|string|max:100',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);

        $carouselSlide->update($validated);

        return response()->json(['message' => 'Slide actualizado.', 'slide' => $carouselSlide]);
    }

    public function destroy(CarouselSlide $carouselSlide)
    {
        $carouselSlide->delete();

        return response()->json(['message' => 'Slide eliminado.']);
    }
}
