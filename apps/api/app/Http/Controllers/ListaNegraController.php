<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\ListaNegra;

class ListaNegraController extends Controller
{
    public function index()
    {
        return response()->json(ListaNegra::with(['institucion', 'solicitud', 'sancionador'])->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'institucion_id' => 'required|exists:instituciones,id',
            'solicitud_id' => 'nullable|exists:solicitudes,id',
            'motivo' => 'required|string',
            'fecha_inicio_sancion' => 'required|date',
            'fecha_fin_sancion' => 'nullable|date|after_or_equal:fecha_inicio_sancion',
            'activa' => 'boolean'
        ]);

        $validated['sancionado_por'] = $request->user()->id; 

        $sancion = ListaNegra::create($validated);

        return response()->json([
            'message' => 'Institución agregada a la Lista Negra',
            'sancion' => $sancion
        ], 201);
    }
    
    public function update(Request $request, ListaNegra $listaNegra)
    {
        $validated = $request->validate([
            'activa' => 'required|boolean',
            'fecha_fin_sancion' => 'nullable|date',
            'motivo' => 'nullable|string'
        ]);

        $listaNegra->update($validated);

        return response()->json([
            'message' => 'Estado de sanción actualizado',
            'sancion' => $listaNegra
        ]);
    }
}
