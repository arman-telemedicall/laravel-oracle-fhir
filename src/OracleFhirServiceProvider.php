<?php

namespace Telemedicall\OracleFhir;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Telemedicall\OracleFhir\Controllers\UserController;

class OracleFhirServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/Oracle-fhir.php' => config_path('Oracle-fhir.php'),
        ], 'Oracle-fhir-config');

        $this->publishes([
            __DIR__.'/Database/Migrations' => database_path('migrations'),
        ], 'Oracle-fhir-migrations');

		Route::get('/jwks/{clientId}', [UserController::class, 'jwks']);

        Route::prefix('fhir/R4')
            ->middleware('web')           // applies session, CSRF, etc.
            ->group(function () {
                Route::get('/jwks/{clientId}', [UserController::class, 'jwks'])->name('OracleFhir.jwks');

                Route::get('/Callback', [UserController::class, 'Callback'])->name('OracleFhir.Callback');
            });
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/Oracle-fhir.php',
            'OracleFhir'
        );

        $this->app->singleton(UserController::class, function ($app) {
            return new UserController(config('OracleFhir'));
        });

        $this->app->alias(UserController::class, 'OracleFhir');
    }
}
