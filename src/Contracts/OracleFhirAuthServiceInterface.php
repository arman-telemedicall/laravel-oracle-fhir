<?php

namespace Teleminergmbh\OracleFhir\Contracts;

interface OracleFhirAuthServiceInterface
{
    public function getSystemAccessToken(string $clientId, string $tenantId): string;

    public function getSmartAccessToken(string $clientId, string $tenantId, string $ownerKey): string;
}
