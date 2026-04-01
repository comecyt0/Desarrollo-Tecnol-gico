<?php

namespace App\Http\Controllers\Evaluaciones;

use App\Http\Controllers\Controller;
use App\Models\AsignacionEvaluador;
use App\Models\Dictamen;
use App\Models\Ministracion;
use App\Models\Solicitud;
use App\Models\ProgramaCriterioEvaluacion;
use App\Models\SolicitudCriterioEvaluacion;
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

        $asignacion->load([
            'solicitud',
            'solicitud.institucion',
            'solicitud.convocatoria',
            'solicitud.convocatoria.tipoPrograma',
            'dictamen'
        ]);
        return response()->json($asignacion);
    }

    /**
     * Store the technical dictamen (Formato B) for a specific assignment.
     * Soporta criterios dinámicos (BD-driven) y fallback a legacy 4-field hardcoded.
     */
    public function saveDictamen(Request $request, AsignacionEvaluador $asignacion)
    {
        // Validate assignment belongs to user
        if ($asignacion->evaluador_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Load solicitud with convocatoria.tipoPrograma
        $asignacion->load(['solicitud.convocatoria.tipoPrograma']);
        $solicitud = $asignacion->solicitud;
        $tipoPrograma = $solicitud->convocatoria?->tipoPrograma;

        // Determinar si el programa tiene criterios dinámicos
        $isDynamic = false;
        if ($tipoPrograma) {
            $criteriosDinamicos = ProgramaCriterioEvaluacion::where(
                'tipo_programa_id',
                $tipoPrograma->id
            )->exists();
            $isDynamic = $criteriosDinamicos;
        }

        // Validar según path
        if ($isDynamic) {
            // Path dinámico: array de criterios_puntajes
            $request->validate([
                'criterios_puntajes' => 'required|array|min:1',
                'criterios_puntajes.*.criterio_id' => 'required|integer|exists:programa_criterios_evaluacion,id',
                'criterios_puntajes.*.puntaje_obtenido' => 'required|numeric|min:0',
                'comentarios_justificacion' => 'required|string|max:2000'
            ]);
        } else {
            // Path legacy: 4 campos hardcodeados
            $request->validate([
                'criterio_1_puntaje' => 'required|numeric|min:0|max:25',
                'criterio_2_puntaje' => 'required|numeric|min:0|max:25',
                'criterio_3_puntaje' => 'required|numeric|min:0|max:25',
                'criterio_4_puntaje' => 'required|numeric|min:0|max:25',
                'comentarios_justificacion' => 'required|string|max:2000'
            ]);
        }

        DB::beginTransaction();
        try {
            $dictamenData = [
                'asignacion_id' => $asignacion->id,
                'comentarios_justificacion' => $request->comentarios_justificacion,
            ];

            if ($isDynamic) {
                // Dynamic path: calcular puntaje_total desde array de criterios
                $puntajeTotal = 0;
                foreach ($request->criterios_puntajes as $cp) {
                    $puntajeTotal += $cp['puntaje_obtenido'];
                }
                $dictamenData['puntaje_total'] = $puntajeTotal;
            } else {
                // Legacy path: usa los 4 campos hardcodeados
                $dictamenData['criterio_1_puntaje'] = $request->criterio_1_puntaje;
                $dictamenData['criterio_2_puntaje'] = $request->criterio_2_puntaje;
                $dictamenData['criterio_3_puntaje'] = $request->criterio_3_puntaje;
                $dictamenData['criterio_4_puntaje'] = $request->criterio_4_puntaje;
                // puntaje_total se calcula en Model::boot()
            }

            $dictamen = Dictamen::create($dictamenData);

            // Si es dynamic path, guardar los criterios individuales
            if ($isDynamic) {
                foreach ($request->criterios_puntajes as $cp) {
                    SolicitudCriterioEvaluacion::create([
                        'solicitud_id' => $solicitud->id,
                        'criterio_id' => $cp['criterio_id'],
                        'puntaje_obtenido' => $cp['puntaje_obtenido'],
                    ]);
                }
            }

            // Update assignment status
            $asignacion->update(['estado' => 'concluido']);

            // Actualizar estado de la solicitud
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
            if ($solicitud->user) {
                $solicitud->user->notify(new SolicitudEstadoActualizado($solicitud));
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
