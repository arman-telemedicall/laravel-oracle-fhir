# Laravel FHIR Client

A Laravel package for integrating with Oracle's FHIR API, supporting both **system-level OAuth client credentials flow** (JWT assertion) and **SMART on FHIR patient launch** (authorization code + PKCE).

Features:
- Client credentials token management with JWT assertion
- JWKS endpoint for public key distribution
- Patient endpoint
- Basic Patient resource creation
- SMART on FHIR authorization code flow with PKCE
- Session & token persistence in database

## Requirements

- PHP >= 8.2
- Laravel 11 or 12
- OpenSSL extension (for JWT signing)
- Valid Oracle FHIR application credentials & RSA key pair

## Installation
```bash
composer require teleminergmbh/laravel-oracle-fhir
```

If you are developing locally without publishing the package, see `DEVELOPMENT.md` for a Composer `path` repository setup.

Create keys on server
```bash
openssl genpkey -algorithm RSA -out private.key -pkeyopt rsa_keygen_bits:2048
openssl rsa -in private.key -pubout -out public.key
```

Set `.env` parameters
```bash
ORACLE_FHIR_TOKEN_URL=https://fhir-ehr.cerner.com/r4/
ORACLE_FHIR_AUTH_URL=https://fhir-ehr.cerner.com/r4/
ORACLE_FHIR_FHIR_BASE=https://fhir-ehr.cerner.com/r4/

ORACLE_FHIR_PRIVATE_KEY_PATH=/home/admin/domains/telemedicall.com/etc/private.key
ORACLE_FHIR_PUBLIC_KEY_PATH=/home/admin/domains/telemedicall.com/etc/public.key

ORACLE_FHIR_COOKIE_DOMAIN=.telemedicall.com
ORACLE_FHIR_ALLOWED_ROOT=telemedicall.com

ORACLE_FHIR_TOKEN_STORE_DRIVER=cache

ORACLE_FHIR_DB_CONNECTION=mysql
ORACLE_FHIR_DB_TABLE=oracle_fhir_tokens


```

## Publish assets
Publish the configuration file and migration:

```bash
# Publish config
php artisan vendor:publish --tag=laravel-oracle-fhir-config

# Publish migration (oracle_fhir_tokens table)
php artisan vendor:publish --tag=oracle-fhir-migrations
```

# Run the migration:
```bash
php artisan migrate
```
This creates the `oracle_fhir_tokens` table used for token persistence when using the `database` TokenStore driver.

# Basic Usage
Via Service Class / Facade

```php
$service = app('OracleFhir');
return $service->fhir()->getPatientById($clientId, $tenantId, $patientId);
```

Dynamic connection (per-user overrides)
```php
$userId = (string) $user->id; // from your app DB

$oracle = app('OracleFhir')->connection([
    'connection_key' => $userId, // used to isolate token storage
    'token_url' => $user->token_url,
    'fhir_base' => $user->fhir_base,
    'oauth_scope' => $user->oauth_scope,
    'private_key_path' => $user->private_key_path,
    'public_key_path' => $user->public_key_path,
]);

return $oracle->fhir()->getPatientById($clientId, $tenantId, $patientId);
```

You can also access tokens directly:

```php
$accessToken = $oracle->systemAccessToken($clientId);
$smartAccessToken = $oracle->smartAccessToken($clientId, 'owner1');
```

Direct Controller Usage
```php
use Teleminergmbh\OracleFhir\Controllers\UserController;
$overrides = [
        "auth_url" => "https://fhir.com",
    ];
$controller = app()->make(UserController::class, ['overrides' => $overrides]);

return $controller->smartLaunch("4383e929-5eb1-4aca-817c-4cd2769a917f");
```

Request config resolver (SMART/JWKS)
```php
use Illuminate\Http\Request;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirRequestConfigResolverInterface;

app()->bind(OracleFhirRequestConfigResolverInterface::class, function () {
    return new class implements OracleFhirRequestConfigResolverInterface
    {
        public function resolveForRequest(Request $request, ?string $clientId = null): array
        {
            $connectionId = $request->query('connectionId');
            if (! is_string($connectionId) || $connectionId === '') {
                return [];
            }

            // Load your provider connection here (from your app DB) and return overrides.
            return [
                'connection_key' => $connectionId,
                // 'token_url' => ...,
                // 'auth_url' => ...,
                // 'fhir_base' => ...,
                // 'private_key_path' => ...,
                // 'public_key_path' => ...,
            ];
        }
    };
});
```

Defined routes:
```php
Route::prefix(config('laravel-oracle-fhir.routes.prefix', 'fhir/R4'))
    ->middleware(config('laravel-oracle-fhir.routes.middleware', 'web'))
    ->group(function () {
        Route::get('/jwks/{clientId}', [UserController::class, 'jwks'])->name('OracleFhir.jwks');
        Route::get('/smart/launch/{clientId}', [UserController::class, 'smartLaunch'])->name('OracleFhir.smart.launch');
        Route::get('/smart/callback', [UserController::class, 'smartCallback'])->name('OracleFhir.smart.callback');
    });
```

Then link to `/{prefix}/jwks/{clientId}` (by default: `oracle/fhir/R4/jwks/your-client-id`).

## Important Notes

Tokens are stored via the configured TokenStore driver (`cache` or `database`).
If you use the `database` driver, publish and run the migration to create the `oracle_fhir_tokens` table.
If you use the `database` driver, database host/port/credentials are taken from your application’s normal Laravel database configuration; the package only needs `ORACLE_FHIR_DB_CONNECTION` (and optionally `ORACLE_FHIR_DB_TABLE`).
Backwards compatibility: if you do not use `connection([...])` or a request resolver, the package continues to use the published Laravel config values as before.
For production, always use HTTPS.
Oracle sandbox: https://fhir-open.cerner.com/r4/
Full Oracle FHIR documentation:https://docs.oracle.com/en/industries/health/millennium-platform-apis/mfrap/op-patientid-get.html
