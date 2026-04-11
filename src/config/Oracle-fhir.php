<?php

return [
    'token_url' => 'https://authorization.cerner.com/tenants/e573b5d9-449b-411c-b6af-73f7fedafc83/protocols/oauth2/profiles/smart-v2/token',
    'auth_url'  => 'https://authorization.cerner.com/tenants/e573b5d9-449b-411c-b6af-73f7fedafc83/protocols/oauth2/profiles/smart-v2/personas/provider/authorize',
    'fhir_base'      => env('ORACLE_FHIR_BASE', 'https://fhir-open.cerner.com/r4/e573b5d9-449b-411c-b6af-73f7fedafc83'),

    'jwt_alg'                 => 'RS384',
    'jwt_kid'                 => 'Oracle-key',
    'jwt_exp_seconds'         => 300,

    // Very important → should be OUTSIDE version control!
    'private_key_path'        => env('ORACLE_PRIVATE_KEY_PATH', '/home/admin/domains/telemedicall.com/etc/private.key'),
    'public_key_path'         => env('ORACLE_PUBLIC_KEY_PATH', '/home/admin/domains/telemedicall.com/etc/public.key'),

    'session_cookie_lifetime' => 3600,
    'cookie_domain'           => env('ORACLE_COOKIE_DOMAIN', '.telemedicall.com'),

    'oauth_scope'             => 'system/Patient.read system/Patient.search system/Patient.write',
    'smart_scope'             => 'openid fhirUser patient.read patient.search launch launch/patient',
    'code_challenge_method'   => 'S384',

    'list_code'               => 'patients',
    'list_subject'            => env('ORACLE_LIST_SUBJECT', ''), // e.g. Practitioner/123
    'list_status'             => env('ORACLE_LIST_STATUS', 'current'),

    'system_lists_identifier' => 'urn:oid:1.2.840.114350.1.13.0.1.7.2.806567|5332',
    'user_lists_identifier'   => 'urn:oid:1.2.840.114350.1.13.0.1.7.2.698283|9192',

    'db' => [
        'connection' => env('ORACLE_DB_CONNECTION', 'mysql'),
        //dedicated connection:
        'host'     => env('ORACLE_DB_HOST', 'localhost'),
        'port'     => env('ORACLE_DB_PORT', '3306'),
        'database' => env('ORACLE_DB_DATABASE', ''),
        'username' => env('ORACLE_DB_USERNAME', ''),
        'password' => env('ORACLE_DB_PASSWORD', ''),
    ],
    'allowed_root' => env('ORACLE_ALLOWED_ROOT', 'telemedicall.com'),
];
