<?php

namespace Teleminergmbh\OracleFhir\Contracts;

interface OracleFhirTokenStoreInterface
{
    /**
     * @return array{access_token?:string, refresh_token?:string, expires_at?:int, scope?:string, token_type?:string, patient_id?:string}
     */
    public function get(string $clientId, string $tenantId, string $flow, string $ownerKey): array;

    /**
     * @param  array{access_token?:string, refresh_token?:string, expires_at?:int, scope?:string, token_type?:string, patient_id?:string}  $data
     */
    public function put(string $clientId, string $tenantId, string $flow, string $ownerKey, array $data, ?int $ttlSeconds = null): void;

    public function forget(string $clientId, string $tenantId, string $flow, string $ownerKey): void;
}
