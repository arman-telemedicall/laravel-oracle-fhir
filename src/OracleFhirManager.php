<?php

namespace Teleminergmbh\OracleFhir;

use Teleminergmbh\OracleFhir\Contracts\OracleFhirHttpClientInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirTokenStoreInterface;
use Teleminergmbh\OracleFhir\Services\OracleFhirAuthService;
use Teleminergmbh\OracleFhir\Services\OracleFhirFhirClient;

class OracleFhirManager
{
    public function __construct(
        protected OracleFhirHttpClientInterface $http,
        protected OracleFhirTokenStoreInterface $tokens,
    ) {}

    /** @param array<string, mixed> $overrides */
    public function makeAuth(array $overrides = []): OracleFhirAuthService
    {
        return new OracleFhirAuthService($this->http, $this->tokens, $overrides);
    }

    /** @param array<string, mixed> $overrides */
    public function makeFhir(array $overrides = []): OracleFhirFhirClient
    {
        return new OracleFhirFhirClient($this->http, $this->makeAuth($overrides), $overrides);
    }

    /** @param array<string, mixed> $overrides */
    public function makeOracle(array $overrides = []): OracleFhir
    {
        $auth = $this->makeAuth($overrides);
        $fhir = new OracleFhirFhirClient($this->http, $auth, $overrides);

        return new OracleFhir($this->http, $auth, $fhir);
    }
}
