<?php

namespace Teleminergmbh\OracleFhir\Services;

use RuntimeException;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirAuthServiceInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirFhirClientInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirHttpClientInterface;
use Teleminergmbh\OracleFhir\Database\Traits\OracleConfigTrait;

class OracleFhirFhirClient implements OracleFhirFhirClientInterface
{
    use OracleConfigTrait;

    public function __construct(
        protected OracleFhirHttpClientInterface $http,
        protected OracleFhirAuthServiceInterface $auth,
        array $overrides = [],
    ) {
        $this->initializeOracleConfig($overrides);
    }
	
	    /** @param array<string, string|null> $query */
    protected function getFhir(string $clientId, string $tenantId, string $path, string $accessToken, array $query = []): string
    {
        $url = rtrim((string) $this->oracleConfig('fhir_base'), '/').$path;
		if($this->oracleConfig('sandbox_enabled')){$url = 'https://fhir-open.cerner.com/r4/ec2458f2-1e24-41c8-b71b-0e701af7583d' . $path;}

        try {
            return $this->http->getRaw($url, array_filter($query, fn ($v) => $v !== null && $v !== ''), [
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/fhir+json',
                'Oracle-Client-ID' => $clientId,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    /** @param array<string, mixed> $payload */
    protected function postFhir(string $clientId, string $tenantId, string $path, string $accessToken, array $payload): array
    {
        $url = rtrim((string) $this->oracleConfig('fhir_base'), '/') . $tenantId .$path;
		if($this->oracleConfig('sandbox_enabled')){$url = 'https://fhir-open.cerner.com/r4/ec2458f2-1e24-41c8-b71b-0e701af7583d' . $path;}
		
        try {
            $body = $this->http->postJson($url, $payload, [
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/fhir+json',
                'Content-Type' => 'application/fhir+json',
                'Oracle-Client-ID' => $clientId,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        return [
            'status' => 200,
            'location' => null,
            'body' => $body,
        ];
    }
	
	public function getPatientById(string $clientId, string $tenantId, string $patientId): string
	{
		if($this->oracleConfig('sandbox_enabled')){$token = 'SandBox';} else {$token = $this->auth->getSystemAccessToken($clientId, $tenantId);}
		
		return $this->getFhir($clientId, $tenantId, '/Patient/' . $patientId, $token, []);
	}
}
