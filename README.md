# Laravel FHIR Client

A Laravel package for integrating with Oracle's FHIR API, supporting both **system-level OAuth client credentials flow** (JWT assertion) and **SMART on FHIR patient launch** (authorization code + PKCE).

Features:
- Client credentials token management with JWT assertion
- JWKS endpoint for public key distribution
- FHIR List searching (system & user lists)
- Patient endpoint
- Basic Patient resource creation
- SMART on FHIR authorization code flow with PKCE
- Session & token persistence in database

## Requirements

- PHP ≥ 8.1
- Laravel ≥ 10.0 (tested up to Laravel 12)
- OpenSSL extension (for JWT signing)
- Valid Oracle FHIR application credentials & RSA key pair

## Installation
Add these to your composer.json:
```bash
"repositories": [
  {
    "type": "vcs",
    "url": "https://github.com/arman-telemedicall/laravel-oracle-fhir"
  }
],
"require": {
  "arman-telemedicall/laravel-oracle-fhir": "dev-main"
}
```
And then,
```bash
composer require arman-telemedicall/laravel-oracle-fhir
composer update
composer dump-autoload
```
Create keys on server
```bash
openssl genpkey –algorithm RSA –out private.key –pkeyopt rsa_keygen_bits:2048 
openssl rsa –in private.key –pubout –out public.key
```

Set .env parameters
```bash
ORACLE_TOKEN_URL=https://authorization.cerner.com/tenants/e573b5d9-449b-411c-b6af-73f7fedafc83/protocols/oauth2/profiles/smart-v2/token
ORACLE_AUTH_URL=https://authorization.cerner.com/tenants/e573b5d9-449b-411c-b6af-73f7fedafc83/protocols/oauth2/profiles/smart-v2/personas/provider/authorize
ORACLE_FHIR_BASE=https://fhir-open.cerner.com/r4/{YOUR_ORACLE_APPLICATION_ID}

ORACLE_PRIVATE_KEY_PATH=/home/admin/domains/telemedicall.com/etc/private.key
ORACLE_PUBLIC_KEY_PATH=/home/admin/domains/telemedicall.com/etc/public.key

ORACLE_COOKIE_DOMAIN=.telemedicall.com
ORACLE_ALLOWED_ROOT=telemedicall.com

ORACLE_DB_CONNECTION=mysql

//Only for dedicated database
ORACLE_DB_HOST=localhost
ORACLE_DB_PORT=3306
ORACLE_DB_DATABASE=
ORACLE_DB_USERNAME=
ORACLE_DB_PASSWORD=


```

## Publish assets
Publish the configuration file and migration:

```bash
# Publish config
php artisan vendor:publish --tag=Oracle-fhir-config

# Publish migration (Oracle_users table)
php artisan vendor:publish --tag=Oracle-fhir-migrations
```

# Run the migration:
```bash
php artisan migrate
```
This creates the oracle_users table used for token & session persistence.

# Basic Usage
Via Service Class / Facade

First add the user to the oracle_users table:
```bash
$service = app('OracleFhir');
$service->AddUser('Clinicians or Administrative Users','A1001.1','1dbdc6e4-555a-4257-9fee-650ed7691ce4','e573b5d9-449b-411c-b6af-73f7fedafc83',);
```
Then use user credentials like Application ID and Client ID for other functions:
```bash
$wellKnown = Http::get('https://fhir-ehr.cerner.com/r4/e573b5d9-449b-411c-b6af-73f7fedafc83/.well-known/smart-configuration')->json();
$overrides = [
    'auth_url'  => $wellKnown['authorization_endpoint'],
    'token_url' => $wellKnown['token_endpoint'],
];
$service = app('OracleFhir');
$service->initializeOracleConfig($overrides);
return $service->Patient('A1001.1','1dbdc6e4-555a-4257-9fee-650ed7691ce4','12724067');
```

Direct Controller Usage
```bash
use Telemedicall\OracleFhir\Controllers\UserController;
$wellKnown = Http::get('https://fhir-ehr.cerner.com/r4/e573b5d9-449b-411c-b6af-73f7fedafc83/.well-known/smart-configuration')->json();
$overrides = [
    'auth_url'  => $wellKnown['authorization_endpoint'],
    'token_url' => $wellKnown['token_endpoint'],
];
$service = new UserController($overrides);

return $service->SmartOnFhir("4383e929-5eb1-4aca-817c-4cd2769a917f");
```

Defined routes:
```bash
Route::prefix('oracle/fhir/R4')
            ->middleware('web')           // applies session, CSRF, etc.
            ->group(function () {
                Route::get('/jwks/{clientId}', [UserController::class, 'jwks'])->name('OracleFhir.jwks');

                Route::get('/Callback', [UserController::class, 'Callback'])->name('OracleFhir.Callback');
				
				Route::get('/Patient/{PatientID}', [UserController::class, 'Patient'])->name('OracleFhir.Patient');
});
```

Then link to /oracle/fhir/R4/jwks/your-client-id.

## Important Notes

Tokens are stored in oracle_users table and associated with a SessionHash cookie.
Session expiration is set to 1 hour by default (configurable via jwt_exp_seconds + buffer).
For production, always use HTTPS (secure cookie flag is enabled).
Oracle sandbox: https://code-console.cerner.com/console/apps
Full Oracle FHIR documentation: https://docs.oracle.com/en/
