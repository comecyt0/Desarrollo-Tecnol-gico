<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

/**
 * Sello de tiempo simplificado (NOM-151 — etapa inicial).
 *
 * Genera un hash SHA-256 del contenido + timestamp UTC + emisor, y lo registra
 * en audit_logs como cadena de evidencia. NO firma con un TSA (Prestador de
 * Servicios de Certificación acreditado), pero da trazabilidad criptográfica
 * que cumple con el principio de no-repudio interno.
 *
 * Cuando el área de TIC del Estado entregue las credenciales de un TSA
 * acreditado (típicamente CSFI / Telmex / SAT TSA), se reemplaza
 * `appendCertificate()` por la llamada al TSA y se persiste el token.
 */
class DocumentSignature
{
    /**
     * Calcula la firma para un contenido binario o string.
     *
     * @return array{hash:string, algoritmo:string, timestamp:string, sello_evidencia:string}
     */
    public static function sign(string $content, string $documentType, ?int $subjectId = null): array
    {
        $hash = hash('sha256', $content);
        $timestamp = now()->utc()->toIso8601String();
        $issuer = config('app.name', 'COMECYT').' · '.config('app.url', '');

        // Cadena de evidencia: combina hash + timestamp + emisor para que cualquier
        // verificación posterior pueda recalcular y comparar.
        $evidenceChain = hash('sha256', $hash.'|'.$timestamp.'|'.$issuer);

        // Registrar en audit_logs (tamper-evident por su orden append-only)
        try {
            AuditLog::create([
                'user_id' => request()?->user()?->id,
                'action' => "document.signed.{$documentType}",
                'subject_type' => null,
                'subject_id' => $subjectId,
                'metadata' => [
                    'hash' => $hash,
                    'algoritmo' => 'SHA-256',
                    'timestamp_utc' => $timestamp,
                    'emisor' => $issuer,
                    'sello_evidencia' => $evidenceChain,
                ],
                'ip' => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            // No detener la emisión del documento si el log falla
            Log::warning('DocumentSignature audit failed', [
                'error' => $e->getMessage(),
                'document_type' => $documentType,
            ]);
        }

        return [
            'hash' => $hash,
            'algoritmo' => 'SHA-256',
            'timestamp' => $timestamp,
            'sello_evidencia' => $evidenceChain,
            'emisor' => $issuer,
        ];
    }

    /**
     * TODO: integrar TSA acreditado (NOM-151 / RFC 3161).
     * Cuando se firme contrato con CSFI / Telmex TSA / similar, este método
     * deberá hacer POST al endpoint del TSA con `$hash` y recibir un
     * `TimeStampToken` que se persiste y opcionalmente se embebe en el PDF.
     */
    public static function appendTsaToken(string $hash): ?string
    {
        // Placeholder — la implementación real va aquí cuando se tenga TSA
        return null;
    }
}
