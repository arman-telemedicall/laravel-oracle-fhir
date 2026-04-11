<?php

namespace Telemedicall\OracleFhir\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use RuntimeException;

class UserController extends BaseController
{
    public function __construct(array $overrides = [])
    {
        parent::__construct($overrides);
    }

    /**
     * JWKS endpoint - returns public key in JWK format
     */
    public function jwks(string $clientId): JsonResponse
    {
        $publicKeyPath = $this->OracleConfig('public_key_path');

        $publicKeyPem = file_get_contents($publicKeyPath);
        if ($publicKeyPem === false) {
            return $this->error("Unable to read public key file", 500);
        }

        $jwk = $this->pemToJwk($publicKeyPem, $clientId);

        return $this->json([
            'keys' => [$jwk]
        ]);
    }

    /**
     * Add / register a new Oracle user record
     */
    public function AddUser(
        ?string $appAudience,
        ?string $userId,
        ?string $clientId,
        ?string $ApplicationId,
        ?string $token = null,
        ?string $sessionHash = null,
        ?string $sessionExp = null
    ): int {
        $insertedId = $this->db->connection()
            ->table('oracle_users')
            ->insertGetId([
                'AppAudience'   => $appAudience,
                'UserID'        => $userId,
                'ClientID'      => $clientId,
                'ApplicationID' => $ApplicationId,
                'Token'         => $token,
                'DateRegistered'=> now(),
                'SessionHash'   => $sessionHash,
                'SessionEXP'    => $sessionExp ? now()->parse($sessionExp) : null,
            ]);

        return $insertedId;
    }

    public function ListSearch(string $userId, string $clientId): string|JsonResponse
    {
        $token = $this->ensureValidToken($userId, $clientId);

        $url = $this->buildListUrl([
            'code'      => $this->OracleConfig('list_code'),
            'identifier' => $this->OracleConfig('system_lists_identifier'),
            'subject'   => $this->OracleConfig('list_subject'),
            'status'    => $this->OracleConfig('list_status'),
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/fhir+json',
        ])->get($url);

        if ($response->failed()) {
            return $this->error("FHIR List search failed", $response->status());
        }

        return $response->body();
    }

    public function MyListSearch(string $userId, string $clientId): string|JsonResponse
    {
        $token = $this->ensureValidToken($userId, $clientId);

        $url = $this->buildListUrl([
            'code'       => $this->OracleConfig('list_code'),
            'identifier' => $this->OracleConfig('user_lists_identifier'),
            'status'     => $this->OracleConfig('list_status'),
        ]);

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/fhir+json',
        ])->get($url);

        if ($response->failed()) {
            return $this->error("My Lists search failed", $response->status());
        }

        return $response->body();
    }

    public function ListRead(string $userId, string $clientId, ?string $listId = null): string|JsonResponse
    {
        if (!$listId) {
            return $this->error("No List ID received", 400);
        }

        $token = $this->ensureValidToken($userId, $clientId);

        $url = $this->OracleConfig('fhir_base') . "/List/{$listId}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/fhir+json',
        ])->get($url);

        if ($response->failed()) {
            return $this->error("List read failed", $response->status());
        }

        return $response->body();
    }

    public function PatientSummary(string $userId, string $clientId, ?string $patientId = null): string|JsonResponse
    {
        $token = $this->ensureValidToken($userId, $clientId);

        if (!$patientId) {
            $patientId = Session::get('PatientID');
            if (!$patientId) {
                return $this->error("No patient selected from Oracle", 404);
            }
        }

        $url = $this->OracleConfig('fhir_base') . "/Patient/{$patientId}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/fhir+json',
        ])->get($url);

        if ($response->failed()) {
            return $this->error("Patient summary failed", $response->status());
        }

        return $response->body();
    }

    public function PatientCreate(string $userId, string $clientId, array $patientData): JsonResponse
    {
        $token = $this->ensureValidToken($userId, $clientId);

        $required = ['familyName', 'givenName', 'gender', 'birthDate'];
        foreach ($required as $field) {
            if (empty($patientData[$field])) {
                return $this->error("Missing required field: {$field}", 400);
            }
        }

        $patient = $this->buildPatientResource($patientData);

        $url = $this->OracleConfig('fhir_base') . '/Patient';

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/fhir+json',
            'Content-Type'  => 'application/fhir+json',
        ])->post($url, $patient);

        if ($response->failed()) {
            return $this->error(
                "Oracle API Error ({$response->status()}): " . $response->body(),
                $response->status()
            );
        }

        $location = $response->header('Location');

        return $this->json([
            'status'   => $response->status(),
            'location' => $location,
            'body'     => $response->json(),
        ], $response->status());
    }

	public function Patient(string $PatientID)
	{
		$response = Http::withHeaders(['Accept' => 'application/fhir+json',])->get('https://fhir-open.cerner.com/r4/ec2458f2-1e24-41c8-b71b-0e701af7583d/Patient/'.$PatientID);

        return $response;
	}

    // ────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────

    private function buildListUrl(array $params): string
    {
        $query = http_build_query(array_filter($params));
        return $this->OracleConfig('fhir_base') . '/List' . ($query ? "?{$query}" : '');
    }

    private function buildPatientResource(array $data): array
    {
        $patient = [
            'resourceType' => 'Patient',
            'identifier'   => array_values(array_filter([
                !empty($data['identifierSystem1']) && !empty($data['identifierValue1'])
                    ? ['use' => 'usual', 'system' => $data['identifierSystem1'], 'value' => $data['identifierValue1']]
                    : null,
                !empty($data['identifierSystem2']) && !empty($data['identifierValue2'])
                    ? ['use' => 'usual', 'system' => $data['identifierSystem2'], 'value' => $data['identifierValue2']]
                    : null,
            ])),

            'name' => [
                [
                    'use'    => 'official',
                    'family' => $data['familyName'],
                    'given'  => [$data['givenName']],
                    'suffix' => !empty($data['suffix']) ? [$data['suffix']] : [],
                ],
            ],

            'telecom' => array_values(array_filter([
                !empty($data['phone'])
                    ? ['system' => 'phone', 'value' => $data['phone'], 'use' => 'home']
                    : null,
                !empty($data['email'])
                    ? ['system' => 'email', 'value' => $data['email']]
                    : null,
            ])),

            'gender'    => $data['gender'],
            'birthDate' => $data['birthDate'],

            'address' => [
                [
                    'use'        => 'home',
                    'line'       => array_values(array_filter([
                        $data['addressLine1'] ?? null,
                        $data['addressLine2'] ?? null,
                    ])),
                    'city'       => $data['city'] ?? '',
                    'state'      => $data['state'] ?? '',
                    'postalCode' => $data['postalCode'] ?? '',
                    'country'    => $data['country'] ?? '',
                ],
            ],

            'maritalStatus' => [
                'text' => $data['maritalStatus'] ?? '',
            ],
        ];

        return $patient;
    }

    // ────────────────────────────────────────────────
    //  SMART on FHIR related methods
    // ────────────────────────────────────────────────

    public function SmartOnFhir(string $clientId)
    {
        Session::put('ClientID', $clientId);

        $host = strtolower(parse_url(request()->url(), PHP_URL_HOST));
        $allowedRoot = $this->OracleConfig('allowed_root');
        if ($host !== $allowedRoot && !str_ends_with($host, '.' . $allowedRoot)) {
            return $this->error("Invalid host for redirect URI", 400);
        }

        $scheme = request()->secure() ? 'https' : 'http';
        $redirectUri = "{$scheme}://{$host}/oracle/fhir/R4/Callback";

        $state = bin2hex(random_bytes(16));
        Session::put('oauth2_state', $state);

        $codeVerifier  = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        Session::put('code_verifier', $codeVerifier);

        $params = [
            'client_id'             => $clientId,
            'scope'                 => $this->OracleConfig('smart_scope'),
            'response_type'         => 'code',
            'redirect_uri'          => $redirectUri,
            'state'                 => $state,
            'code_challenge_method' => $this->OracleConfig('code_challenge_method'),
            'code_challenge'        => $codeChallenge,
            'aud'                   => $this->OracleConfig('fhir_base'),
        ];

        $authUrl = $this->OracleConfig('auth_url') . '?' . http_build_query($params);

        return redirect()->away($authUrl);
    }

    public function Callback()
    {
		$code = request()->query('code'); 
		if (!$code) error;
		$state = request()->query('state'); 
		if ($state !== session('oauth2_state')) error;
		
        $clientId = Session::get('ClientID');

        if (!$code) {
            return $this->error("No authorization code received", 400);
        }

        $codeVerifier = Session::get('code_verifier');
        if (!$codeVerifier) {
            return $this->error("Missing PKCE verifier (session expired)", 400);
        }

        $host = strtolower(parse_url(request()->url(), PHP_URL_HOST));
        $allowedRoot = $this->OracleConfig('allowed_root');;
        if ($host !== $allowedRoot && !str_ends_with($host, '.' . $allowedRoot)) {
            return $this->error("Invalid host for redirect URI", 400);
        }

        $scheme = request()->secure() ? 'https' : 'http';
        $redirectUri = "{$scheme}://{$host}/oracle/fhir/R4/Callback";

        $response = Http::asForm()->post($this->OracleConfig('token_url'), [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => $redirectUri,
            'client_id'     => $clientId,
            'code_verifier' => $codeVerifier,
            'aud'           => $this->OracleConfig('fhir_base'),
        ]);

        if ($response->failed()) {
            return $this->error("Token exchange failed: " . $response->body(), $response->status());
        }

        $tokenData = $response->json();

        Session::put('access_token', $tokenData['access_token'] ?? null);
        Session::put('patient_id', $tokenData['patient'] ?? null);

        return $this->SmartPatientSummary(
            $tokenData['access_token'],
            $tokenData['patient']
        );
    }

    public function SmartPatientSummary(?string $accessToken = null, ?string $patientId = null): string|JsonResponse
    {
        if (!$accessToken || !$patientId) {
            return $this->error("Missing access_token or patient_id", 400);
        }

        $url = $this->OracleConfig('fhir_base') . "/Patient/{$patientId}/\$summary";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$accessToken}",
            'Accept'        => 'application/fhir+json',
        ])->get($url);

        if ($response->failed()) {
            return $this->error("SMART Patient summary failed", $response->status());
        }

        return $response->body();
    }

    // ────────────────────────────────────────────────
    //  PKCE helpers
    // ────────────────────────────────────────────────

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha384', $verifier, true)), '+/', '-_'), '=');
    }
}