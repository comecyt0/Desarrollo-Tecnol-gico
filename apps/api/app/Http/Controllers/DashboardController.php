<?php

namespace App\Http\Controllers;

use App\Models\AsignacionEvaluador;
use App\Models\Convocatoria;
use App\Models\Informe;
use App\Models\Ministracion;
use App\Models\Solicitud;
use Illuminate\Http\Request;
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
                'color' => 'text-blue-500',
            ],
            [
                'title' => 'Solicitudes en Revisión',
                'value' => (string) Solicitud::whereIn('estado', ['enviada', 'observada'])->count(),
                'icon' => 'FileText',
                'color' => 'text-amber-500',
            ],
            [
                'title' => 'Proyectos Evaluándose',
                'value' => (string) Solicitud::where('estado', 'en_evaluacion')->count(),
                'icon' => 'Users',
                'color' => 'text-accent',
            ],
            [
                'title' => 'Dictámenes Aprobados',
                'value' => (string) Solicitud::where('estado', 'aprobada')->count(),
                'icon' => 'CheckCircle2',
                'color' => 'text-green-600',
            ],
            [
                'title' => 'Ministraciones Pendientes',
                'value' => (string) Ministracion::where('estado', 'pendiente')->count(),
                'icon' => 'Clock',
                'color' => 'text-orange-500',
            ],
            [
                'title' => 'Informes Entregados',
                'value' => (string) Solicitud::where('estado_informe', 'entregado')->count(),
                'icon' => 'FileText',
                'color' => 'text-blue-600',
            ],
            [
                'title' => 'Pagos Completados',
                'value' => (string) Ministracion::where('estado', 'pagada')->count(),
                'icon' => 'CheckCircle2',
                'color' => 'text-emerald-600',
            ],
        ];

        return response()->json($stats);
    }

    /**
     * Estadísticas para el Revisor
     * - nuevos: solicitudes enviadas y sin revisar aún (estado='enviada')
     * - en_subsanacion: solicitudes con observaciones que esperan correcciones (estado='observada')
     * - pendientes_urgentes: solicitudes observadas hace más de 7 días sin reenvío (antiguas)
     */
    public function revisorStats()
    {
        // Solicitudes con estado 'enviada' sin revisar
        $nuevos = Solicitud::where('estado', 'enviada')->count();

        // Solicitudes con estado 'observada' en proceso de subsanación
        $en_subsanacion = Solicitud::where('estado', 'observada')->count();

        // Solicitudes 'observada' que llevan más de 7 días sin reenvío (urgentes)
        $pendientes_urgentes = Solicitud::where('estado', 'observada')
            ->where('updated_at', '<', now()->subDays(7))
            ->count();

        $stats = [
            'nuevos' => $nuevos,
            'en_subsanacion' => $en_subsanacion,
            'pendientes_urgentes' => $pendientes_urgentes,
        ];

        return response()->json($stats);
    }

    /**
     * Estadísticas para el Evaluador
     * - por_iniciar: asignaciones con estado 'notificado' (sin empezar)
     * - en_progreso: asignaciones SIN estado 'notificado' y SIN estado 'concluido' (en proceso)
     * - evaluadas: asignaciones con estado 'concluido' (evaluación completada)
     */
    public function evaluadorStats(Request $request)
    {
        $user = $request->user();

        $stats = [
            'por_iniciar' => AsignacionEvaluador::where('evaluador_id', $user->id)
                ->where('estado', 'asignado')
                ->count(),
            'en_progreso' => AsignacionEvaluador::where('evaluador_id', $user->id)
                ->where('estado', 'evaluando')
                ->count(),
            'evaluadas' => AsignacionEvaluador::where('evaluador_id', $user->id)
                ->where('estado', 'concluido')
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
            DB::raw('count(*) as value')
        )
            ->groupBy('label')
            ->orderBy(DB::raw('min(created_at)'))
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
        $atrasados = Informe::where('estado', 'pendiente')
            ->where('fecha_limite_entrega', '<', now()->subDays(20))
            ->count();

        if ($atrasados > 0) {
            $alerts[] = [
                'type' => 'error',
                'message' => "<strong>{$atrasados} Instituciones</strong> excedieron el límite de 20 días para el informe final.",
                'color' => 'bg-red-50',
                'dot' => 'bg-red-500',
            ];
        }

        // 2. Convocatorias por cerrar (en menos de 7 días)
        $porCerrar = Convocatoria::where('estado', 'activa')
            ->whereBetween('fecha_cierre', [now(), now()->addDays(7)])
            ->get();

        foreach ($porCerrar as $conv) {
            $days = now()->diffInDays($conv->fecha_cierre);
            $alerts[] = [
                'type' => 'warning',
                'message' => "La convocatoria \"{$conv->titulo}\" cierra en {$days} días.",
                'color' => 'bg-blue-50',
                'dot' => 'bg-blue-500',
            ];
        }

        return response()->json($alerts);
    }
}
