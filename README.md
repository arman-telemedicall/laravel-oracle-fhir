# Laravel FHIR Client

A Laravel package for integrating with Oracle's FHIR API, supporting both **system-level OAuth client credentials flow** (JWT assertion) and **SMART on FHIR patient launch** (authorization code + PKCE).

Features:
- Client credentials token management with JWT assertion
- JWKS endpoint for public key distribution
- FHIR List searching (system & user lists)
- Patient $summary endpoint
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
ORACLE_TOKEN_URL=https://fhir.Oracle.com/interconnect-fhir-oauth/oauth2/token
ORACLE_AUTH_URL=https://fhir.Oracle.com/interconnect-fhir-oauth/oauth2/authorize
ORACLE_FHIR_BASE=https://fhir-open.cerner.com/r4/

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
This creates the Oracle_users table used for token & session persistence.

# Basic Usage
Via Service Class / Facade

```bash
$overrides = [
        "token_url" => "https://fhir.Oracle.com/interconnect-fhir-oauth/oauth2/authorize",
    ];
$service = app('OracleFhir');
$service->initializeOracleConfig($overrides);
return $service->ListSearch("A1000.1","68965e9f-a9c8-480b-a169-518b0cf9f68f");
```

Direct Controller Usage
```bash
use Telemedicall\OracleFhir\Controllers\UserController;
$overrides = [
        "auth_url" => "https://fhir.com",
    ];
$service = new UserController($overrides);

return $service->SmartOnFhir("4383e929-5eb1-4aca-817c-4cd2769a917f");
```

Defined routes:
```bash
Route::prefix('fhir/R4')
            ->middleware('web')           // applies session, CSRF, etc.
            ->group(function () {
                Route::get('/jwks/{clientId}', [UserController::class, 'jwks'])->name('OracleFhir.jwks');

                Route::get('/Callback', [UserController::class, 'Callback'])->name('OracleFhir.Callback');
});
```

Then link to /fhir/R4/jwks/your-client-id.

## Important Notes

Tokens are stored in Oracle_users table and associated with a SessionHash cookie.
Session expiration is set to 1 hour by default (configurable via jwt_exp_seconds + buffer).
For production, always use HTTPS (secure cookie flag is enabled).
Oracle sandbox: https://code-console.cerner.com/console/apps
Full Oracle FHIR documentation: https://docs.oracle.com/en/
