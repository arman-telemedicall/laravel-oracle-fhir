<?php

namespace Teleminergmbh\OracleFhir\Contracts;

interface OracleFhirFhirClientInterface
{
	public function getPatientById(string $clientId, string $tenantId, string $patientId): string;
}
