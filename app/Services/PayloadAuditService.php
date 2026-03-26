<?php

namespace App\Services;

use App\Models\PayloadAudit;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class PayloadAuditService
{
    public function __construct(
        protected bool $enabled,
        protected int $retentionDays
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            enabled: (bool) config('integration.payload_audit_enabled', false),
            retentionDays: (int) config('integration.payload_audit_retention_days', 7)
        );
    }

    /**
     * Log request/response metadata only (no full payload) to integration log.
     */
    public function logMetadata(string $direction, ?string $entityRef, ?string $status, ?int $responseCode, ?string $requestId = null): void
    {
        Log::channel('integration')->info('API call', [
            'direction' => $direction,
            'entity_ref' => $entityRef,
            'status' => $status,
            'response_code' => $responseCode,
            'request_id' => $requestId ?? uniqid('req_', true),
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Store encrypted payload for troubleshooting (when enabled or on error). Limited retention.
     */
    public function storeEncrypted(string $direction, ?string $entityRef, ?string $status, ?int $responseCode, array $payload, bool $onError = false): void
    {
        if (! $this->enabled && ! $onError) {
            return;
        }

        $expiresAt = $this->retentionDays > 0 ? now()->addDays($this->retentionDays) : null;

        try {
            $encrypted = Crypt::encryptString(json_encode($payload));

            PayloadAudit::create([
                'direction' => $direction,
                'entity_ref' => $entityRef,
                'status' => $status,
                'response_code' => $responseCode,
                'payload_encrypted' => $encrypted,
                'expires_at' => $expiresAt,
            ]);
        } catch (\Throwable $e) {
            Log::channel('integration')->warning('PayloadAudit: failed to store encrypted payload', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete expired payload audit records.
     */
    public function cleanupExpired(): int
    {
        return PayloadAudit::whereNotNull('expires_at')->where('expires_at', '<', now())->delete();
    }
}
