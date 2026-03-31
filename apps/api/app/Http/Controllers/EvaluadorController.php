<?php

namespace App\Http\Controllers;

use App\Models\AsignacionEvaluador;
use App\Models\Solicitud;
use App\Models\User;
use App\Models\NotificacionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EvaluadorController extends Controller
{
    /**
     * Las asignaciones del evaluador autenticado
     */
    public function asignaciones()
    {
        $user = Auth::user();
        
        $asignaciones = AsignacionEvaluador::with([
            'solicitud.institucion',
            'solicitud.convocatoria',
            'solicitud.areaConocimiento',
            'dictamen',
        ])
        ->where('evaluador_id', $user->id)
        ->orderBy('created_at', 'desc')
        ->get();

        return response()->json($asignaciones);
    }

    /**
     * Detalle de una asignación específica
     */
    public function show(AsignacionEvaluador $asignacion)
    {
        $asignacion->load([
            'solicitud.user',
            'solicitud.institucion',
            'solicitud.convocatoria',
            'solicitud.areaConocimiento',
            'evaluador',
            'dictamen',
        ]);

        return response()->json($asignacion);
    }

    /**
     * Guarda o actualiza el dictamen de una asignación
     */
    public function saveDictamen(Request $request, AsignacionEvaluador $asignacion)
    {
        $validated = $request->validate([
            'criterios'      => 'required|array',
            'comentarios'    => 'nullable|string',
            'resultado'      => 'required|in:aprobado,rechazado,condicionado',
            'puntaje_total'  => 'required|numeric|min:0|max:100',
        ]);

        // Actualizar o crear dictamen
        $dictamen = $asignacion->dictamen()->updateOrCreate(
            ['asignacion_id' => $asignacion->id],
            [
                'evaluador_id'  => Auth::id(),
                'criterios'     => json_encode($validated['criterios']),
                'comentarios'   => $validated['comentarios'],
                'resultado'     => $validated['resultado'],
                'puntaje_total' => $validated['puntaje_total'],
            ]
        );

        // Marcar asignación como concluida
        $asignacion->update(['estado' => 'concluido']);

        // Actualizar estado de la solicitud
        $asignacion->solicitud->update([
            'estado' => $validated['resultado'] === 'aprobado' ? 'aprobada' : 'rechazada',
        ]);

        // Notificar al solicitante
        NotificacionLog::create([
            'user_id'        => $asignacion->solicitud->user_id,
            'solicitud_id'   => $asignacion->solicitud_id,
            'tipo'           => 'dictamen_emitido',
            'asunto'         => "Dictamen emitido para tu solicitud {$asignacion->solicitud->folio}",
            'mensaje'        => "Tu proyecto ha sido dictaminado como: " . strtoupper($validated['resultado']),
            'correo_destino' => $asignacion->solicitud->user->email ?? '',
            'enviado'        => false,
        ]);

        return response()->json([
            'message' => 'Dictamen guardado correctamente.',
            'dictamen' => $dictamen,
        ]);
    }

    /**
     * ADMIN: Asignar un evaluador a una solicitud
     */
    public function asignar(Request $request)
    {
        $validated = $request->validate([
            'solicitud_id'  => 'required|exists:solicitudes,id',
            'evaluador_id'  => 'required|exists:users,id',
            'fecha_limite'  => 'required|date|after:today',
        ]);

        // Verificar que el usuario sea evaluador (rol_id = 3)
        $evaluador = User::findOrFail($validated['evaluador_id']);
        if ($evaluador->rol_id !== 3) {
            return response()->json(['message' => 'El usuario seleccionado no tiene rol de Evaluador.'], 422);
        }

        // Verificar que no exista ya esa asignación
        $existente = AsignacionEvaluador::where('solicitud_id', $validated['solicitud_id'])
            ->where('evaluador_id', $validated['evaluador_id'])
            ->first();

        if ($existente) {
            return response()->json(['message' => 'Este evaluador ya está asignado a esta solicitud.'], 422);
        }

        $asignacion = AsignacionEvaluador::create([
            'solicitud_id'  => $validated['solicitud_id'],
            'evaluador_id'  => $validated['evaluador_id'],
            'fecha_limite'  => $validated['fecha_limite'],
            'estado'        => 'notificado',
        ]);

        // Actualizar solicitud a en_evaluacion
        Solicitud::find($validated['solicitud_id'])->update(['estado' => 'en_evaluacion']);

        // Notificar al evaluador
        NotificacionLog::create([
            'user_id'        => $validated['evaluador_id'],
            'solicitud_id'   => $validated['solicitud_id'],
            'tipo'           => 'nueva_asignacion',
            'asunto'         => "Nueva solicitud asignada para evaluación",
            'mensaje'        => "Se te ha asignado una solicitud para dictaminación. Fecha límite: {$validated['fecha_limite']}",
            'correo_destino' => $evaluador->email,
            'enviado'        => false,
        ]);

        return response()->json([
            'message'    => 'Evaluador asignado correctamente.',
            'asignacion' => $asignacion->load(['evaluador', 'solicitud']),
        ], 201);
    }

    /**
     * ADMIN: Desasignar un evaluador de una solicitud
     */
    public function desasignar(AsignacionEvaluador $asignacion)
    {
        if ($asignacion->estado === 'concluido') {
            return response()->json(['message' => 'No se puede desasignar una evaluación ya concluida.'], 422);
        }

        $asignacion->delete();

        return response()->json(['message' => 'Evaluador desasignado correctamente.']);
    }

    /**
     * Estadísticas del evaluador
     */
    public function stats()
    {
        $user = Auth::user();
        
        $stats = [
            'por_iniciar' => AsignacionEvaluador::where('evaluador_id', $user->id)
                ->where('estado', 'notificado')->count(),
            'en_progreso' => AsignacionEvaluador::where('evaluador_id', $user->id)
                ->where('estado', 'evaluando')->count(),
            'evaluadas'   => AsignacionEvaluador::where('evaluador_id', $user->id)
                ->where('estado', 'concluido')->count(),
        ];

        return response()->json($stats);
    }
}
