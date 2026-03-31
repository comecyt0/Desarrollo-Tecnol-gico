<?php

namespace App\Http\Controllers;

use App\Models\Solicitud;
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
            'tipo' => 'required|string|in:informe_final,carta_compromiso,caratula_banco,constancia_fiscal,factura_institucion'
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
        
        // Store in local public disk: storage/app/public/documentos/{solicitud_id}
        $file->storeAs("public/documentos/{$solicitud->id}", $filename);
        
        // This generates /storage/documentos/{id}/{filename} because the disk points to storage/app/public
        $publicUrl = Storage::url("documentos/{$solicitud->id}/{$filename}");

        if ($tipo === 'informe_final') {
            $solicitud->informe_final_url = $publicUrl;
            $solicitud->fecha_entrega_informe = now();
            $solicitud->estado_informe = 'entregado';
            $solicitud->save();
        } else {
            // It is a financial document (Ministracion)
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
        }

        return response()->json([
            'message' => 'Documento subido con exito',
            'url' => $publicUrl,
            'tipo' => $tipo
        ]);
    }
}
