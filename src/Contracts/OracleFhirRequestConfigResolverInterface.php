<?php

namespace Teleminergmbh\OracleFhir\Contracts;

use Illuminate\Http\Request;

interface OracleFhirRequestConfigResolverInterface
{
    /**
     * @return array<string, mixed>
     */
    public function resolveForRequest(Request $request, ?string $clientId = null, ?string $tenantId = null): array;
}
