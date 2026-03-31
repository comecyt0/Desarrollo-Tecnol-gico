<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Convocatoria;
use App\Models\Solicitud;
use App\Models\AsignacionEvaluador;
use App\Models\Dictamen;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Estadísticas para el Administrador
     */
    public function adminStats()
    {
        $stats = [
            [
                'title' => 'Convocatorias Activas',
                'value' => (string) Convocatoria::where('estado', 'activa')->count(),
                'icon' => 'Bookmark',
                'color' => 'text-blue-500'
            ],
            [
                'title' => 'Solicitudes en Revisión',
                'value' => (string) Solicitud::whereIn('estado', ['enviada', 'en_revision', 'observada'])->count(),
                'icon' => 'FileText',
                'color' => 'text-amber-500'
            ],
            [
                'title' => 'Proyectos Evaluándose',
                'value' => (string) Solicitud::where('estado', 'en_evaluacion')->count(),
                'icon' => 'Users',
                'color' => 'text-accent'
            ],
            [
                'title' => 'Dictámenes Aprobados',
                'value' => (string) Solicitud::where('estado', 'aprobada')->count(),
                'icon' => 'CheckCircle2',
                'color' => 'text-green-600'
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Estadísticas para el Revisor
     */
    public function revisorStats()
    {
        $stats = [
            'nuevos' => Solicitud::where('estado', 'enviada')->count(),
            'en_subsanacion' => Solicitud::where('estado', 'observada')->count(),
            'pendientes_urgentes' => Solicitud::where('estado', 'en_revision')->count(), // Ejemplo de lógica
        ];

        return response()->json($stats);
    }

    /**
     * Estadísticas para el Evaluador
     */
    public function evaluadorStats(Request $request)
    {
        $user = $request->user();
        
        // Asumiendo que el usuario tiene un modelo Evaluador asociado
        $evaluador = $user->evaluador;

        if (!$evaluador) {
            return response()->json([
                'por_iniciar' => 0,
                'en_progreso' => 0,
                'evaluadas' => 0
            ]);
        }

        $stats = [
            'por_iniciar' => AsignacionEvaluador::where('evaluador_id', $evaluador->id)
                ->where('estado', 'notificado')
                ->count(),
            'en_progreso' => AsignacionEvaluador::where('evaluador_id', $evaluador->id)
                ->where('estado', 'en_proceso')
                ->count(),
            'evaluadas' => AsignacionEvaluador::where('evaluador_id', $evaluador->id)
                ->where('estado', 'evaluado')
                ->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Actividad Reciente para Admin
     */
    public function adminActivity()
    {
        // Ejemplo: Solicitudes por mes en el último año
        $activity = Solicitud::select(
            DB::raw("to_char(created_at, 'Mon') as label"),
            DB::raw("count(*) as value")
        )
        ->groupBy('label')
        ->orderBy(DB::raw("min(created_at)"))
        ->get();

        return response()->json($activity);
    }

    /**
     * Alertas de Sistema para Admin
     */
    public function adminAlerts()
    {
        $alerts = [];

        // 1. Instituciones con informes atrasados (> 20 días)
        $atrasados = \App\Models\Informe::where('estado', 'pendiente')
            ->where('fecha_limite_entrega', '<', now()->subDays(20))
            ->count();
        
        if ($atrasados > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "<strong>{$atrasados} Instituciones</strong> excedieron el límite de 20 días para el informe final.",
                'color' => 'bg-red-50',
                'dot' => 'bg-red-500'
            ];
        }

        // 2. Convocatorias por cerrar (en menos de 7 días)
        $porCerrar = \App\Models\Convocatoria::where('estado', 'activa')
            ->whereBetween('fecha_cierre', [now(), now()->addDays(7)])
            ->get();

        foreach ($porCerrar as $conv) {
            $days = now()->diffInDays($conv->fecha_cierre);
            $alerts[] = [
                'type' => 'warning',
                'message' => "La convocatoria \"{$conv->titulo}\" cierra en {$days} días.",
                'color' => 'bg-blue-50',
                'dot' => 'bg-blue-500'
            ];
        }

        return response()->json($alerts);
    }
}
