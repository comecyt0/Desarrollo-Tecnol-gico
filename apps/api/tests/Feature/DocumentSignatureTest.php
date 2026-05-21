<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Support\DocumentSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentSignatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_devuelve_hash_sha256_estable(): void
    {
        $payload = '{"test":true}';

        $a = DocumentSignature::sign($payload, 'test', 1);
        $b = DocumentSignature::sign($payload, 'test', 2);

        // Mismo payload → mismo hash
        $this->assertEquals($a['hash'], $b['hash']);
        $this->assertEquals(64, strlen($a['hash']));
        $this->assertEquals('SHA-256', $a['algoritmo']);
        $this->assertNotEmpty($a['sello_evidencia']);
    }

    public function test_sign_registra_audit_log(): void
    {
        DocumentSignature::sign('payload-x', 'dictamen', 42);

        $log = AuditLog::where('action', 'document.signed.dictamen')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals(42, $log->subject_id);
        $this->assertEquals(hash('sha256', 'payload-x'), $log->metadata['hash']);
        $this->assertArrayHasKey('timestamp_utc', $log->metadata);
        $this->assertArrayHasKey('sello_evidencia', $log->metadata);
    }

    public function test_alteracion_del_payload_cambia_el_hash(): void
    {
        $a = DocumentSignature::sign('original', 'test', 1);
        $b = DocumentSignature::sign('original tampered', 'test', 2);

        $this->assertNotEquals($a['hash'], $b['hash']);
    }

    public function test_tsa_placeholder_devuelve_null(): void
    {
        // Hasta integrar TSA real, debe devolver null sin lanzar
        $this->assertNull(DocumentSignature::appendTsaToken('cualquier-hash'));
    }
}
