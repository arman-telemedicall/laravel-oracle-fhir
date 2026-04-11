<?php

namespace Telemedicall\OracleFhir\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Telemedicall\OracleFhir\Database\OracleDbConnection;
use Telemedicall\OracleFhir\Database\Traits\OracleConfigTrait;

abstract class BaseController
{
    use OracleConfigTrait;

    protected OracleDbConnection $db;

    public function __construct(array $overrides = [])
    {
        $this->initializeOracleConfig($overrides);
        $this->db = new OracleDbConnection($overrides);
    }

    protected function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return $this->json(['error' => $message], $status);
    }

    /**
     * Check / refresh access token
     * Returns token string or throws exception / returns error response
     */
    protected function ensureValidToken(string $userId, string $clientId): string
    {
        $sessionHash = request()->cookie('SessionHash');

        if (session()->has('access_token')) {
            return session('access_token');
        }

        if (!$sessionHash) {
            return $this->authorizeAndStoreToken($userId, $clientId);
        }

        $user = $this->db->connection()
            ->table('oracle_users')
            ->where('SessionHash', $sessionHash)
            ->where('SessionEXP', '>', now())
            ->first();

        if (!$user) {
            return $this->authorizeAndStoreToken($userId, $clientId);
        }

        session(['access_token' => $user->Token]);

        return $user->Token;
    }

    /**
     * Perform client credentials flow with JWT assertion
     */
    private function authorizeAndStoreToken(string $userId, string $clientId): string
    {
        $user = $this->db->connection()
            ->table('oracle_users')
            ->where('UserID', $userId)
            ->where(function ($q) use ($clientId) {
                $q->where('ApplicationID', $clientId)
                  ->orWhere('ClientID', $clientId);
            })
            ->first();

        if (!$user) {
            abort($this->error("User not found", 400));
        }

        $privateKeyPath = $this->OracleConfig('private_key_path');
        $privateKey = file_get_contents($privateKeyPath);

        if ($privateKey === false) {
            abort($this->error("Could not read private key", 500));
        }

        $header = [
            'alg' => $this->OracleConfig('jwt_alg'),
            'typ' => 'JWT',
            'kid' => $this->OracleConfig('jwt_kid'),
        ];

        $now = time();
        $claims = [
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $this->OracleConfig('token_url'),
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + $this->OracleConfig('jwt_exp_seconds', 300),
        ];

        $jwtHeader   = $this->base64UrlEncode(json_encode($header));
        $jwtClaims   = $this->base64UrlEncode(json_encode($claims));
        $unsignedJwt = $jwtHeader . '.' . $jwtClaims;

        $signature = '';
        $success = openssl_sign(
            $unsignedJwt,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA384
        );

        if (!$success) {
            abort($this->error("Failed to sign JWT assertion", 500));
        }

        $jwtAssertion = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

        $response = Http::asForm()->post($this->OracleConfig('token_url'), [
            'grant_type'            => 'client_credentials',
            'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
            'client_assertion'      => $jwtAssertion,
            'scope'                 => $this->OracleConfig('oauth_scope'),
        ]);

        if ($response->failed()) {
            abort($this->error("Token request failed: " . $response->body(), $response->status()));
        }

        $tokenData = $response->json();
        $accessToken = $tokenData['access_token'] ?? null;

        if (!$accessToken) {
            abort($this->error("No access token received", 500));
        }

        $sessionHash = bin2hex(random_bytes(32));

        // Update or insert session data
        $this->db->connection()
            ->table('oracle_users')
            ->updateOrInsert(
                ['id' => $user->id],
                [
                    'Token'       => $accessToken,
                    'SessionHash' => $sessionHash,
                    'SessionEXP'  => now()->addHour(),
                ]
            );

        cookie()->queue(
            'SessionHash',
            $sessionHash,
            $this->OracleConfig('session_cookie_lifetime', 3600),
            '/',
            $this->OracleConfig('cookie_domain'),
            true,   // secure
            true,   // httpOnly
            false,  // raw
            'Lax'   // sameSite
        );

        session(['access_token' => $accessToken]);

        return $accessToken;
    }

    protected function pemToJwk(string $publicKeyPem, string $clientId): array
    {
        $keyResource = openssl_pkey_get_public($publicKeyPem);
        if ($keyResource === false) {
            throw new RuntimeException("Invalid public key");
        }

        $details = openssl_pkey_get_details($keyResource);
        if (!$details || !isset($details['rsa'])) {
            throw new RuntimeException("Failed to extract RSA key details");
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => $this->OracleConfig('jwt_alg'),
            'iss' => $clientId,
            'sub' => $clientId,
            'typ' => 'JWT',
            'kid' => $this->OracleConfig('jwt_kid'),
            'n'   => $this->base64UrlEncode($details['rsa']['n']),
            'e'   => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}