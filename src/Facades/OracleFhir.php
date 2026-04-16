<?php

namespace Teleminergmbh\OracleFhir\Facades;

use Illuminate\Support\Facades\Facade;

class OracleFhir extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Teleminergmbh\OracleFhir\OracleFhir::class;
    }
}
