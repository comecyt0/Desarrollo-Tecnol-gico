<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Models\Solicitud;
use App\Models\Observacion;
use App\Notifications\SolicitudEstadoActualizado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RevisionController extends Controller
{
    /**
     * Get solicitudes pending review (estado: enviada o observada)
     * Nota: 'subsanada' es el estado previo, ahora es 'observada' cuando revisor devuelve para correcciones
     */
    public function pendientes()
    {
        $solicitudes = Solicitud::with(['user', 'institucion', 'convocatoria', 'areaConocimiento'])
            ->whereIn('estado', ['enviada', 'observada'])
            ->orderBy('updated_at', 'asc')
            ->get();

        return response()->json($solicitudes);
    }

    /**
     * Get specific solicitud details for review
     */
    public function show(Solicitud $solicitud)
    {
        $solicitud->load(['user', 'institucion', 'convocatoria', 'areaConocimiento', 'observaciones', 'documentos', 'asignaciones.dictamen', 'ministracion.banco']);
        return response()->json($solicitud);
    }

    /**
     * Approve documental review and pass to Evaluation stage
     */
    public function approve(Request $request, Solicitud $solicitud)
    {
        DB::beginTransaction();
        try {
            $solicitud->update([
                'estado' => 'en_evaluacion'
            ]);

            // Notificar al solicitante
            if ($solicitud->user) {
                $solicitud->user->notify(new SolicitudEstadoActualizado($solicitud));
            }

            DB::commit();
            return response()->json([
                'message' => 'Solicitud aprobada documentalmente y turnada a Evaluación',
                'solicitud' => $solicitud
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al aprobar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Reject or send observations back to Solicitante
     */
    public function observe(Request $request, Solicitud $solicitud)
    {
        $request->validate([
            'observaciones' => 'required|array|min:1',
            'observaciones.*.campo' => 'required|string|max:100',
            'observaciones.*.comentario' => 'required|string|min:10|max:1000',
            'observaciones.*.tipo' => 'nullable|string|in:documental,tecnica,financiera'
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->observaciones as $obsData) {
                Observacion::create([
                    'solicitud_id' => $solicitud->id,
                    'user_id' => $request->user()->id,
                    'campo' => $obsData['campo'] ?? null,
                    'tipo' => $obsData['tipo'] ?? 'documental',
                    'comentario' => $obsData['comentario'],
                    'resuelta' => false
                ]);
            }

            $solicitud->update([
                'estado' => 'observada',
            ]);

            // Notificar al solicitante
            if ($solicitud->user) {
                $solicitud->user->notify(new SolicitudEstadoActualizado($solicitud, "Se han generado observaciones en tu solicitud. Por favor revísalas en el portal."));
            }

            DB::commit();
            return response()->json([
                'message' => 'Solicitud enviada a subsanación institucional con ' . count($request->observaciones) . ' observaciones.',
                'solicitud' => $solicitud->load('observaciones')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al observar: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Approve final report and close project
     */
    public function approveInforme(Solicitud $solicitud)
    {
        $solicitud->update([
            'estado_informe' => 'aprobado',
            'estado' => 'cerrada'
        ]);

        return response()->json(['message' => 'Informe final aprobado. El proyecto ha sido cerrado oficialmente.']);
    }
}
