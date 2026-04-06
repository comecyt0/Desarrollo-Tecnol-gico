<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
use App\Models\SolicitudDocumento;
use App\Models\Ministracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentoUploadController extends Controller
{
    public function upload(Request $request, Solicitud $solicitud)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf|max:5120', // 5MB limit
            'tipo' => 'required|string|max:100'
        ]);

        $user = auth()->user();
        
        // Verification: must be the owner of the request or an admin
        if ($user->role_id !== 1 && $solicitud->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $file = $request->file('file');
        $tipo = $request->input('tipo');
        $extension = $file->getClientOriginalExtension();
        $filename = "{$solicitud->folio}_{$tipo}_" . time() . ".{$extension}";

        // Store in public disk: storage/app/public/documentos/{solicitud_id}
        // Use explicit disk('public') to avoid storing in storage/app/private
        Storage::disk('public')->putFileAs("documentos/{$solicitud->id}", $file, $filename);

        // This generates /storage/documentos/{id}/{filename} via the public disk symlink
        $publicUrl = Storage::disk('public')->url("documentos/{$solicitud->id}/{$filename}");

        // Tipos de documentos con almacenamiento especial
        $tiposEspeciales = [
            'informe_final',
            'carta_compromiso',
            'caratula_banco',
            'constancia_fiscal',
            'factura_institucion',
        ];

        if ($tipo === 'informe_final') {
            // Documento de informe final
            $solicitud->informe_final_url = $publicUrl;
            $solicitud->fecha_entrega_informe = now();
            $solicitud->estado_informe = 'entregado';
            $solicitud->save();
        } elseif (in_array($tipo, ['carta_compromiso', 'caratula_banco', 'constancia_fiscal', 'factura_institucion'])) {
            // Documento financiero (Ministracion)
            $ministracion = Ministracion::firstOrCreate(
                ['solicitud_id' => $solicitud->id],
                ['estado' => 'pendiente']
            );

            switch ($tipo) {
                case 'carta_compromiso':
                    $ministracion->carta_compromiso_url = $publicUrl;
                    break;
                case 'caratula_banco':
                    $ministracion->caratula_banco_url = $publicUrl;
                    break;
                case 'constancia_fiscal':
                    $ministracion->constancia_fiscal_url = $publicUrl;
                    break;
                case 'factura_institucion':
                    $ministracion->factura_institucion_url = $publicUrl;
                    break;
            }
            $ministracion->save();
        } else {
            // Todos los demás documentos van a solicitud_documentos (programa-específicos)
            SolicitudDocumento::create([
                'solicitud_id' => $solicitud->id,
                'tipo' => $tipo,
                'nombre_original' => $file->getClientOriginalName(),
                'url' => $publicUrl,
            ]);
        }

        return response()->json([
            'message' => 'Documento subido con exito',
            'url' => $publicUrl,
            'tipo' => $tipo
        ]);
    }

    /**
     * Delete a document from a solicitud
     */
    public function destroy(Request $request, Solicitud $solicitud, SolicitudDocumento $documento)
    {
        // Verify authorization
        $user = auth()->user();
        if ($user->role_id !== 1 && $solicitud->user_id !== $user->id) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Verify the document belongs to this solicitud
        if ($documento->solicitud_id !== $solicitud->id) {
            return response()->json(['error' => 'Documento no encontrado'], 404);
        }

        // Extract filename from URL and delete from storage
        // URLs are like /storage/documentos/{id}/{filename}
        // We need to convert to public/documentos/{id}/{filename} for the public disk
        $path = str_replace('/storage/', 'documentos/', $documento->url);
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        // Soft delete the record
        $documento->delete();

        return response()->json(['message' => 'Documento eliminado exitosamente']);
    }
}
