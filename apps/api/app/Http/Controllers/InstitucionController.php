<?php

namespace App\Http\Controllers;

use App\Models\Institucion;
use Illuminate\Http\Request;

class InstitucionController extends Controller
{
    public function index()
    {
        return response()->json(Institucion::orderBy('nombre')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre'       => 'required|string|max:255|unique:instituciones,nombre',
            'acronimo'     => 'nullable|string|max:20',
            'municipio_id' => 'nullable|exists:municipios,id',
            'activo'       => 'boolean',
            'activa'       => 'boolean', // Accept both activo and activa
        ]);

        // Map activa to activo if present
        if (isset($validated['activa']) && !isset($validated['activo'])) {
            $validated['activo'] = $validated['activa'];
        }
        unset($validated['activa']);

        $inst = Institucion::create($validated);

        return response()->json(['message' => 'Institución creada', 'institucion' => $inst], 201);
    }

    public function show(Institucion $institucion)
    {
        return response()->json($institucion->load('municipio'));
    }

    public function update(Request $request, Institucion $institucion)
    {
        $validated = $request->validate([
            'nombre'       => 'sometimes|string|max:255|unique:instituciones,nombre,' . $institucion->id,
            'acronimo'     => 'nullable|string|max:20',
            'municipio_id' => 'nullable|exists:municipios,id',
            'activo'       => 'boolean',
            'activa'       => 'boolean', // Accept both activo and activa
        ]);

        // Map activa to activo if present
        if (isset($validated['activa']) && !isset($validated['activo'])) {
            $validated['activo'] = $validated['activa'];
        }
        unset($validated['activa']);

        $institucion->update($validated);

        return response()->json(['message' => 'Institución actualizada', 'institucion' => $institucion]);
    }

    public function destroy(Institucion $institucion)
    {
        $institucion->delete();
        return response()->json(['message' => 'Institución eliminada']);
    }
}
