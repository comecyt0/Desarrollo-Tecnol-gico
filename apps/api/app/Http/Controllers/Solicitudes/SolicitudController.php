<?php

namespace App\Http\Controllers\Solicitudes;

use App\Http\Controllers\Controller;
use App\Models\ListaNegra;
use App\Models\Convocatoria;
use App\Models\Solicitud;
use App\Models\SolicitudCampoDinamico;
use App\Models\SolicitudRubroPresupuesto;
use App\Models\SolicitudMiembroEquipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use App\Notifications\SolicitudEstadoActualizado;
use Illuminate\Validation\ValidationException;

class SolicitudController extends Controller
{
    /**
     * List user's solicitudes
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        $solicitudes = Solicitud::where('user_id', $user->id)
                                ->orderBy('created_at', 'desc')
                                ->get();
                                
        return response()->json($solicitudes);
    }

    /**
     * Create a new solicitud
     */
    public function store(Request $request)
    {
        $request->validate([
            'convocatoria_id' => 'required|exists:convocatorias,id',
            'titulo_proyecto' => 'required|string|max:255',
            'modalidad' => 'nullable|string',
            'area_conocimiento_id' => 'required|integer',
            'descripcion' => 'required|string|max:2000',
            'monto_solicitado' => 'required|numeric|min:1',
        ]);

        $user = $request->user();

        // 🔴 0. Validar que el usuario tenga una institución asignada
        if (!$user->institucion_id) {
            return response()->json([
                'message' => 'No puedes crear solicitudes sin tener una institución asignada en tu perfil.',
                'error'   => 'sin_institucion',
            ], 403);
        }

        // 🔴 1. Validar que la institución del usuario NO esté en Lista Negra
        $enListaNegra = ListaNegra::where('institucion_id', $user->institucion_id)
            ->where('activa', true)
            ->exists();

        if ($enListaNegra) {
            return response()->json([
                'message' => 'Tu institución está inhabilitada para participar en convocatorias COMECYT. Contacta al administrador.',
                'error'   => 'institucion_bloqueada',
            ], 403);
        }

        // 🟢 2. Validar que la convocatoria esté activa
        $convocatoria = Convocatoria::findOrFail($request->convocatoria_id);
        if ($convocatoria->estado !== 'activa') {
            return response()->json(['message' => 'La convocatoria ya no acepta solicitudes.'], 422);
        }
        
        DB::beginTransaction();
        try {
            // Generar folio único: COMECYT-YYYY-RANDOM6
            $year = date('Y');
            $random = strtoupper(Str::random(6));
            $folio = "COMECYT-{$year}-{$random}";

            // Asegurar unicidad (reintento simple si el random ya existe)
            while (Solicitud::where('folio', $folio)->exists()) {
                $random = strtoupper(Str::random(6));
                $folio = "COMECYT-{$year}-{$random}";
            }

            $solicitud = Solicitud::create([
                'folio' => $folio,
                'user_id' => $user->id,
                'institucion_id' => $user->institucion_id,
                'convocatoria_id' => $request->convocatoria_id,
                'titulo_proyecto' => $request->titulo_proyecto,
                'modalidad' => $request->modalidad,
                'descripcion_proyecto' => $request->descripcion,
                'monto_solicitado' => $request->monto_solicitado,
                'area_conocimiento_id' => $request->area_conocimiento_id,
                'estado' => 'borrador',
            ]);

            // Guardar campos dinámicos
            if ($request->has('campos_dinamicos')) {
                foreach ($request->campos_dinamicos as $campo) {
                    SolicitudCampoDinamico::create([
                        'solicitud_id'     => $solicitud->id,
                        'programa_campo_id' => $campo['campo_id'],
                        'valor_texto'      => $campo['valor'],
                    ]);
                }
            }

            // Guardar rubros presupuestales
            if ($request->has('rubros')) {
                foreach ($request->rubros as $rubro) {
                    if (($rubro['monto'] ?? 0) > 0) {
                        SolicitudRubroPresupuesto::create([
                            'solicitud_id'     => $solicitud->id,
                            'rubro_id'         => $rubro['rubro_id'],
                            'monto_solicitado' => $rubro['monto'],
                        ]);
                    }
                }
            }

            // Guardar miembros de equipo (EMP)
            if ($request->has('miembros_equipo')) {
                foreach ($request->miembros_equipo as $i => $miembro) {
                    SolicitudMiembroEquipo::create([
                        'solicitud_id'    => $solicitud->id,
                        'nombre_completo' => $miembro['nombre'],
                        'edad'            => $miembro['edad'] ?? null,
                        'rol_en_equipo'   => $miembro['rol'],
                        'correo'          => $miembro['email'] ?? null,
                        'es_lider'        => $i === 0,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Solicitud creada con éxito en estado borrador.',
                'solicitud' => $solicitud
            ], 201);

        } catch (ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Error fatal en el servidor al crear la solicitud.',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Show a single solicitud with relations (must belong to the authenticated user)
     */
    public function show(Request $request, Solicitud $solicitud)
    {
        if ($solicitud->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $solicitud->load([
            'convocatoria.tipoPrograma',
            'institucion',
            'areaConocimiento',
            'observaciones',
            'documentos',
            'asignaciones.dictamen',
            'ministracion.banco'
        ]);
        
        return response()->json($solicitud);
    }

    /**
     * Transition a solicitud from borrador → enviada
     */
    public function enviar(Request $request, Solicitud $solicitud)
    {
        if ($solicitud->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($solicitud->estado !== 'borrador') {
            return response()->json(['error' => 'Solo las solicitudes en borrador pueden enviarse.'], 422);
        }

        // Validar que todos los documentos obligatorios hayan sido subidos
        $solicitud->load(['documentos', 'convocatoria.tipoPrograma.documentos']);

        $tipoPrograma = $solicitud->convocatoria?->tipoPrograma;
        if ($tipoPrograma) {
            $docsObligatorios = $tipoPrograma->documentos()
                ->where('obligatorio', true)
                ->where('activo', true)
                ->pluck('clave')
                ->toArray();

            $docsSubidos = $solicitud->documentos->pluck('tipo')->toArray();
            $docsFaltantes = array_diff($docsObligatorios, $docsSubidos);

            if (!empty($docsFaltantes)) {
                return response()->json([
                    'error' => 'Faltan documentos obligatorios para enviar la solicitud.',
                    'documentos_faltantes' => array_values($docsFaltantes),
                ], 422);
            }
        }

        $solicitud->update([
            'estado' => 'enviada',
        ]);

        // Notificar al solicitante
        $solicitud->user->notify(new SolicitudEstadoActualizado($solicitud));

        return response()->json(['message' => 'Solicitud enviada exitosamente para revisión.', 'solicitud' => $solicitud]);
    }

    /**
     * Re-send a solicitud from observada back to enviada state
     * Used when solicitant corrects observations and resubmits
     */
    public function reenviar(Request $request, Solicitud $solicitud)
    {
        if ($solicitud->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($solicitud->estado !== 'observada') {
            return response()->json(['error' => 'Solo las solicitudes en observada pueden reenviarse.'], 422);
        }

        $solicitud->update([
            'estado' => 'enviada',
        ]);

        // Notificar al revisor que hay una nueva versión
        $solicitud->user->notify(new SolicitudEstadoActualizado($solicitud));

        return response()->json(['message' => 'Solicitud reenviada exitosamente para re-revisión.', 'solicitud' => $solicitud]);
    }

    /**
     * Submit final report for a project in execution/ministration
     */
    public function submitInforme(Request $request, Solicitud $solicitud)
    {
        if ($solicitud->user_id !== $request->user()->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'informe_final_url' => 'required|string',
        ]);

        $solicitud->update([
            'informe_final_url' => $request->informe_final_url,
            'estado_informe' => 'entregado',
            'fecha_entrega_informe' => now(),
        ]);

        return response()->json([
            'message' => 'Informe final entregado con éxito. Pendiente de validación por COMECYT.',
            'solicitud' => $solicitud
        ]);
    }

    /**
     * Fetch active convocatorias for the form
     */
    public function activeConvocatorias()
    {
        $convocatorias = Convocatoria::with('tipoPrograma')
            ->where('estado', 'activa')
            ->get();
        return response()->json($convocatorias);
    }
}
