<?php

namespace Teleminergmbh\OracleFhir;

use Teleminergmbh\OracleFhir\Contracts\OracleFhirAuthServiceInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirFhirClientInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirHttpClientInterface;

class OracleFhir
{
    public function __construct(
        protected OracleFhirHttpClientInterface $http,
        protected OracleFhirAuthServiceInterface $auth,
        protected OracleFhirFhirClientInterface $fhir,
    ) {}

    /** @param array<string, mixed> $overrides */
    public function connection(array $overrides): self
    {
        return app(OracleFhirManager::class)->makeEpic($overrides);
    }

    public function http(): OracleFhirHttpClientInterface
    {
        return $this->http;
    }

    public function systemAccessToken(string $clientId, string $tenantId): string
    {
        return $this->auth->getSystemAccessToken($clientId, $tenantId);
    }

    public function smartAccessToken(string $clientId, string $tenantId, string $ownerKey): string
    {
        return $this->auth->getSmartAccessToken($clientId, $tenantId, $ownerKey);
    }

    public function fhir(): OracleFhirFhirClientInterface
    {
        return $this->fhir;
    }
}
