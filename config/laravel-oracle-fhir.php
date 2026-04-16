<?php

return [
    'token_url' => env('ORACLE_FHIR_TOKEN_URL', 'https://fhir-ehr.cerner.com/r4/'),
    'auth_url' => env('ORACLE_FHIR_AUTH_URL', 'https://fhir-ehr.cerner.com/r4/'),
    'fhir_base' => env('ORACLE_FHIR_FHIR_BASE', 'https://fhir-ehr.cerner.com/r4/'),
	'sandbox_enabled' => (bool) env('ORACLE_FHIR_SANDBOX_ENABLED', true),

    'jwt_alg' => env('ORACLE_FHIR_JWT_ALG', 'RS384'),
    'jwt_kid' => env('ORACLE_FHIR_JWT_KID', 'Oracle-key'),
    'jwt_exp_seconds' => (int) env('ORACLE_FHIR_JWT_EXP_SECONDS', 300),

    'private_key_path' => env('ORACLE_FHIR_PRIVATE_KEY_PATH'),
    'public_key_path' => env('ORACLE_FHIR_PUBLIC_KEY_PATH'),

    'session_cookie_lifetime' => (int) env('ORACLE_FHIR_SESSION_COOKIE_LIFETIME', 3600),
    'cookie_domain' => env('ORACLE_FHIR_COOKIE_DOMAIN'),

    'oauth_scope' => env('ORACLE_FHIR_OAUTH_SCOPE', 'system/Patient.read system/Patient.search system/Patient.write'),
    'smart_scope' => env('ORACLE_FHIR_SMART_SCOPE', 'openid fhirUser patient.read patient.search launch launch/patient'),
    'code_challenge_method' => env('ORACLE_FHIR_CODE_CHALLENGE_METHOD', 'S256'),

    'smart_flow_ttl_seconds' => (int) env('ORACLE_FHIR_SMART_FLOW_TTL_SECONDS', 600),

    'routes' => [
        'enabled' => (bool) env('ORACLE_FHIR_ROUTES_ENABLED', true),
        'prefix' => env('ORACLE_FHIR_ROUTES_PREFIX', 'oracle/fhir/R4'),
        'middleware' => env('ORACLE_FHIR_ROUTES_MIDDLEWARE', 'web'),
    ],

    'migrations' => [
        'enabled' => (bool) env('ORACLE_FHIR_MIGRATIONS_ENABLED', true),
    ],

    'token_store' => [
        'driver' => env('ORACLE_FHIR_TOKEN_STORE_DRIVER', 'cache'),

        'cache' => [
            'store' => env('ORACLE_FHIR_CACHE_STORE', null),
            'prefix' => env('ORACLE_FHIR_CACHE_PREFIX', 'oracle_fhir'),
            'lock_seconds' => (int) env('ORACLE_FHIR_LOCK_SECONDS', 10),
            'expires_buffer_seconds' => (int) env('ORACLE_FHIR_EXPIRES_BUFFER_SECONDS', 60),
        ],

        'database' => [
            'connection' => env('ORACLE_FHIR_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
            'table' => env('ORACLE_FHIR_DB_TABLE', 'oracle_fhir_tokens'),
        ],
    ],

    'list_code' => env('ORACLE_FHIR_LIST_CODE', 'patients'),
    'list_subject' => env('ORACLE_FHIR_LIST_SUBJECT', ''),
    'list_status' => env('ORACLE_FHIR_LIST_STATUS', 'current'),

    'system_lists_identifier' => env('ORACLE_FHIR_SYSTEM_LISTS_IDENTIFIER', 'urn:oid:1.2.840.114350.1.13.0.1.7.2.806567|5332'),
    'user_lists_identifier' => env('ORACLE_FHIR_USER_LISTS_IDENTIFIER', 'urn:oid:1.2.840.114350.1.13.0.1.7.2.698283|9192'),

    'allowed_root' => env('ORACLE_FHIR_ALLOWED_ROOT'),

    'base_url' => env('ORACLE_FHIR_BASE_URL', null),
    'api_key' => env('ORACLE_FHIR_API_KEY', null),
    'timeout' => (int) env('ORACLE_FHIR_TIMEOUT', 30),

    'cache' => [
        'enabled' => (bool) env('ORACLE_FHIR_CACHE_ENABLED', false),
        'ttl' => (int) env('ORACLE_FHIR_CACHE_TTL', 300),
    ],
];
