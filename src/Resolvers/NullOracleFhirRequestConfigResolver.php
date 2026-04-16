<?php

namespace Teleminergmbh\OracleFhir\Resolvers;

use Illuminate\Http\Request;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirRequestConfigResolverInterface;

class NullOracleFhirRequestConfigResolver implements OracleFhirRequestConfigResolverInterface
{
    public function resolveForRequest(Request $request, ?string $clientId = null, ?string $tenantId = null): array
    {
        return [];
    }
}
