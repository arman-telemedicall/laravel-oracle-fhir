<?php
namespace Telemedicall\OracleFhir\Facades;

use Illuminate\Support\Facades\Facade;

class OracleFhir extends Facade {
	
    protected static function getFacadeAccessor() 
	{
        return \Telemedicall\OracleFhir\Services\OracleFhirService::class;
    }
}