<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Convenio;
use App\Models\Solicitud;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ConvenioController extends Controller
{
    /**
     * List all convenios
     */
    public function index()
    {
        $convenios = Convenio::with('solicitud.institucion')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($convenios);
    }

    /**
     * Get a specific convenio
     */
    public function show(Convenio $convenio)
    {
        $convenio->load('solicitud.institucion', 'solicitud.convocatoria', 'ministeraciones');
        return response()->json($convenio);
    }

    /**
     * Generate a convenio for an approved solicitud
     *
     * Called by: Admin when solicitud is aprobada
     * POST /admin/solicitudes/{id}/generar-convenio
     */
    public function generate(Request $request, Solicitud $solicitud)
    {
        // Validate that solicitud is approved
        if ($solicitud->estado !== 'aprobada') {
            return response()->json([
                'error' => 'La solicitud debe estar aprobada para generar convenio.',
                'current_estado' => $solicitud->estado,
            ], 422);
        }

        // Check if convenio already exists
        if ($solicitud->convenio) {
            return response()->json([
                'error' => 'Esta solicitud ya tiene un convenio.',
                'convenio_id' => $solicitud->convenio->id,
            ], 422);
        }

        $request->validate([
            'monto_aprobado' => 'required|numeric|min:1',
            'num_tranches' => 'nullable|integer|min:1|max:12',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        DB::beginTransaction();
        try {
            // Generate unique convenio number
            $year = date('Y');
            $count = Convenio::whereYear('created_at', $year)->count() + 1;
            $numero_convenio = "COMECYT-{$year}-" . str_pad($count, 3, '0', STR_PAD_LEFT);

            // Create convenio
            $convenio = Convenio::create([
                'solicitud_id' => $solicitud->id,
                'numero_convenio' => $numero_convenio,
                'estado' => 'borrador',
                'monto_aprobado' => $request->monto_aprobado,
                'num_tranches' => $request->num_tranches ?? 1,
                'fecha_generacion' => now(),
                'observaciones' => $request->observaciones,
            ]);

            // Generate content
            $this->generateConvenioContent($convenio);

            DB::commit();

            return response()->json([
                'message' => 'Convenio generado exitosamente.',
                'convenio' => $convenio->fresh(),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al generar convenio: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update convenio details
     */
    public function update(Request $request, Convenio $convenio)
    {
        $request->validate([
            'estado' => 'nullable|in:borrador,firmado,vigente,cerrado',
            'observaciones' => 'nullable|string',
            'fecha_firma' => 'nullable|date',
        ]);

        $convenio->update($request->only('estado', 'observaciones', 'fecha_firma'));

        return response()->json(['message' => 'Convenio actualizado.', 'convenio' => $convenio]);
    }

    /**
     * Delete convenio (only if borrador)
     */
    public function destroy(Convenio $convenio)
    {
        if ($convenio->estado !== 'borrador') {
            return response()->json([
                'error' => 'Solo se pueden eliminar convenios en estado borrador.',
            ], 422);
        }

        $convenio->delete();
        return response()->json(['message' => 'Convenio eliminado.']);
    }

    /**
     * Generate convenio content (text-based for MVP)
     */
    private function generateConvenioContent(Convenio $convenio)
    {
        $sol = $convenio->solicitud;
        $inst = $sol->institucion;

        $text = "ACUERDO DE ASIGNACION DE RECURSOS\n\n";
        $text .= "Número de Convenio: {$convenio->numero_convenio}\n";
        $text .= "Fecha: " . now()->format('d/m/Y') . "\n\n";
        $text .= "PARTES:\n";
        $text .= "1. COMECYT (Consejo Mexiquense de Ciencia y Tecnología)\n";
        $text .= "2. {$inst->nombre}\n\n";
        $text .= "OBJETO:\n";
        $text .= "Asignación de recursos para: {$sol->titulo_proyecto}\n";
        $text .= "Folio: {$sol->folio}\n";
        $text .= "Convocatoria: {$sol->convocatoria->nombre}\n\n";
        $text .= "MONTO: \${$convenio->monto_aprobado}\n";
        $text .= "TRANCHES: {$convenio->num_tranches}\n\n";
        $text .= "TÉRMINOS:\n";
        $text .= "1. Uso exclusivo para proyecto aprobado\n";
        $text .= "2. Entrega de reportes según calendario\n";
        $text .= "3. Institución no en lista negra COMECYT\n";
        $text .= "4. Requiere firma de autoridades\n\n";
        $text .= "OBSERVACIONES:\n{$convenio->observaciones}\n\n";
        $text .= "---\nDocumento generado automáticamente por Sistema COMECYT\n";
        $text .= "Requiere firma de autoridades competentes para validez legal.";

        // Save text file as "PDF"
        $path = "public/convenios/{$sol->id}";
        Storage::disk('public')->makeDirectory($path);

        $filename = "convenio_{$convenio->numero_convenio}.txt";
        Storage::disk('public')->put("{$path}/{$filename}", $text);

        $convenio->update([
            'pdf_url' => "/storage/convenios/{$sol->id}/{$filename}",
        ]);
    }
}
