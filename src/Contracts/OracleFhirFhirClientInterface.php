<?php

namespace Teleminergmbh\OracleFhir\Contracts;

interface OracleFhirFhirClientInterface
{
	public function getPatientById(string $clientId, string $tenantId, string $patientId): string;
	
	public function getObservationsList(string $clientId, string $tenantId, string $patientId): string;

	/** @param array<string, mixed> $patientData */
    public function patientCreateSystem(string $clientId, string $tenantId, array $patientData): array;
}
