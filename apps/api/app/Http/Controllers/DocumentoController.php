<?php

namespace App\Http\Controllers;

use App\Models\Dictamen;
use App\Models\Solicitud;
use App\Exports\SolicitudesExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;

class DocumentoController extends Controller
{
    /**
     * Generate PDF for a specific Technical Dictamen
     */
    public function downloadDictamen(Dictamen $dictamen)
    {
        $dictamen->load(['asignacion.solicitud.institucion', 'asignacion.solicitud.convocatoria']);
        
        $pdf = Pdf::loadView('pdfs.dictamen', compact('dictamen'));
        
        return $pdf->download("Dictamen_{$dictamen->asignacion->solicitud->folio}.pdf");
    }

    /**
     * Generate PDF for a specific Convenio
     */
    public function downloadConvenio(Solicitud $solicitud)
    {
        $solicitud->load(['user', 'institucion', 'convocatoria']);
        
        if ($solicitud->estado !== 'aprobada' && $solicitud->estado !== 'convenio') {
            return response()->json(['error' => 'La solicitud debe estar aprobada para generar el convenio.'], 403);
        }

        $pdf = Pdf::loadView('pdfs.convenio', compact('solicitud'));
        
        return $pdf->download("Convenio_{$solicitud->folio}.pdf");
    }

    /**
     * Export all solicitudes to Excel for Admin
     */
    public function exportExcel()
    {
        return Excel::download(new SolicitudesExport, 'sabana_solititudes_comecyt.xlsx');
    }
}
