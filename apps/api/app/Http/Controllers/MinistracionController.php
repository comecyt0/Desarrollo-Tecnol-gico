<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Ministracion;

class MinistracionController extends Controller
{
    public function index()
    {
        return response()->json(Ministracion::with(['solicitud.convocatoria', 'banco'])->orderBy('id', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'solicitud_id' => 'required|exists:solicitudes,id',
            'banco_id' => 'nullable|exists:bancos,id',
            'cuenta_clabe' => 'nullable|string|max:18',
            'numero_cuenta' => 'nullable|string|max:50',
            'titular_cuenta' => 'nullable|string|max:255',
            'estado' => 'nullable|in:pendiente,revision,autorizada,pagada,rechazada'
        ]);

        $ministracion = Ministracion::create($validated);

        return response()->json([
            'message' => 'Ministración creada con éxito',
            'ministracion' => $ministracion
        ], 201);
    }

    public function show(Ministracion $ministracion)
    {
        return response()->json($ministracion->load(['solicitud', 'banco']));
    }

    public function update(Request $request, Ministracion $ministracion)
    {
        $validated = $request->validate([
            'estado' => 'in:pendiente,revision,autorizada,pagada,rechazada',
            'banco_id' => 'nullable|exists:bancos,id',
            'cuenta_clabe' => 'nullable|string|max:18',
            'numero_cuenta' => 'nullable|string|max:50',
            'observaciones' => 'nullable|string',
            'carta_compromiso_aprobada' => 'boolean'
        ]);

        $ministracion->update($validated);

        return response()->json([
            'message' => 'Ministración actualizada con éxito',
            'ministracion' => $ministracion
        ]);
    }
}
