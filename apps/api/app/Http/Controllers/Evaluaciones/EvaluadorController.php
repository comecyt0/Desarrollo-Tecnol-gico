<?php

namespace App\Http\Controllers\Evaluaciones;

use App\Http\Controllers\Controller;
use App\Models\AsignacionEvaluador;
use App\Models\Dictamen;
use App\Models\Ministracion;
use App\Models\Solicitud;
use App\Notifications\DictamenGenerado;
use App\Notifications\SolicitudEstadoActualizado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluadorController extends Controller
{
    /**
     * Get solicitudes assigned to the authenticated evaluator
     */
    public function asignaciones(Request $request)
    {
        $evaluadorUser = $request->user();
        
        $asignaciones = AsignacionEvaluador::with(['solicitud', 'solicitud.institucion'])
            ->where('evaluador_id', $evaluadorUser->id)
            ->orderBy('estado', 'desc') // asignado, evaluando, concluido
            ->get();
            
        return response()->json($asignaciones);
    }

    /**
     * Get a single assignment detail with full solicitud relations
     */
    public function show(Request $request, AsignacionEvaluador $asignacion)
    {
        if ($asignacion->evaluador_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $asignacion->load(['solicitud', 'solicitud.institucion', 'solicitud.convocatoria', 'dictamen']);
        return response()->json($asignacion);
    }

    /**
     * Store the technical dictamen (Formato B) for a specific assignment
     */
    public function saveDictamen(Request $request, AsignacionEvaluador $asignacion)
    {
        // Require all 4 standardized criteria scores (0 to 25 pts)
        $request->validate([
            'criterio_1_puntaje' => 'required|numeric|min:0|max:25',
            'criterio_2_puntaje' => 'required|numeric|min:0|max:25',
            'criterio_3_puntaje' => 'required|numeric|min:0|max:25',
            'criterio_4_puntaje' => 'required|numeric|min:0|max:25',
            'comentarios_justificacion' => 'required|string|max:2000'
        ]);

        // Validate assignment belongs to user
        if ($asignacion->evaluador_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        DB::beginTransaction();
        try {
            // Save Dictamen (Model boot handles puntaje_total and sujeto_apoyo calculate)
            $dictamen = Dictamen::create([
                'asignacion_id' => $asignacion->id,
                'criterio_1_puntaje' => $request->criterio_1_puntaje,
                'criterio_2_puntaje' => $request->criterio_2_puntaje,
                'criterio_3_puntaje' => $request->criterio_3_puntaje,
                'criterio_4_puntaje' => $request->criterio_4_puntaje,
                'comentarios_justificacion' => $request->comentarios_justificacion,
            ]);

            // Update assignment status
            $asignacion->update(['estado' => 'concluido']);

            // Actualizar estado de la solicitud
            $solicitud = $asignacion->solicitud;
            if ($dictamen->sujeto_apoyo) {
                $solicitud->update([
                    'estado' => 'aprobada',
                    'etapa_actual' => 'convenio'
                ]);

                // CREACIÓN AUTOMÁTICA DE REGISTRO DE MINISTRACIÓN
                Ministracion::firstOrCreate(
                    ['solicitud_id' => $solicitud->id],
                    ['estado' => 'pendiente']
                );
            } else {
                $solicitud->update(['estado' => 'rechazada']);
            }

            // Notificar al solicitante
            if ($asignacion->solicitud->user) {
                $asignacion->solicitud->user->notify(new SolicitudEstadoActualizado($asignacion->solicitud));
            }

            DB::commit();

            return response()->json([
                'message' => 'Dictamen guardado exitosamente.',
                'dictamen' => $dictamen->load('asignacion')
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al guardar dictamen: ' . $e->getMessage()], 500);
        }
    }
}
