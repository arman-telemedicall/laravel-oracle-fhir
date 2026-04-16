<?php

namespace Teleminergmbh\OracleFhir\TokenStores;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirTokenStoreInterface;

class DatabaseTokenStore implements OracleFhirTokenStoreInterface
{
    public function __construct(
        protected string $connection,
        protected string $table,
    ) {}

    public function get(string $clientId, string $tenantId, string $flow, string $ownerKey): array
    {
        $row = $this->query()
            ->where('client_id', $clientId)
			->where('tenant_id', $tenantId)
            ->where('flow', $flow)
            ->where('owner_key', $ownerKey)
            ->first();

        if (! $row) {
            return [];
        }

        $data = [
            'access_token' => is_string($row->access_token ?? null) ? $row->access_token : null,
            'refresh_token' => is_string($row->refresh_token ?? null) ? $row->refresh_token : null,
            'expires_at' => is_numeric($row->expires_at ?? null) ? (int) $row->expires_at : null,
            'scope' => is_string($row->scope ?? null) ? $row->scope : null,
            'token_type' => is_string($row->token_type ?? null) ? $row->token_type : null,
            'patient_id' => is_string($row->patient_id ?? null) ? $row->patient_id : null,
        ];

        if (is_int($data['expires_at']) && $data['expires_at'] <= time()) {
            return [];
        }

        return array_filter($data, fn ($v) => $v !== null);
    }

    public function put(string $clientId, string $tenantId, string $flow, string $ownerKey, array $data, ?int $ttlSeconds = null): void
    {
        $expiresAt = $data['expires_at'] ?? null;
        if (! is_int($expiresAt) && $ttlSeconds !== null) {
            $expiresAt = time() + max(0, $ttlSeconds);
        }

        $this->query()->updateOrInsert(
            [
                'client_id' => $clientId,
				'tenant_id' => $tenantId,
                'flow' => $flow,
                'owner_key' => $ownerKey,
            ],
            [
                'access_token' => is_string($data['access_token'] ?? null) ? $data['access_token'] : null,
                'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
                'expires_at' => is_int($expiresAt) ? $expiresAt : null,
                'scope' => is_string($data['scope'] ?? null) ? $data['scope'] : null,
                'token_type' => is_string($data['token_type'] ?? null) ? $data['token_type'] : null,
                'patient_id' => is_string($data['patient_id'] ?? null) ? $data['patient_id'] : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function forget(string $clientId, string $tenantId, string $flow, string $ownerKey): void
    {
        $this->query()
            ->where('client_id', $clientId)
			->where('tenant_id', $tenantId)
            ->where('flow', $flow)
            ->where('owner_key', $ownerKey)
            ->delete();
    }

    protected function query(): Builder
    {
        return DB::connection($this->connection)->table($this->table);
    }
}
