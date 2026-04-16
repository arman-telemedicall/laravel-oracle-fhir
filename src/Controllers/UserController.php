<?php

namespace Teleminergmbh\OracleFhir\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirHttpClientInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirRequestConfigResolverInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirTokenStoreInterface;

class UserController extends BaseController
{
    private const SMART_FLOW_CACHE_PREFIX = 'oracle_fhir:smart_flow:';

    public function __construct(
        protected OracleFhirHttpClientInterface $http,
        protected OracleFhirTokenStoreInterface $tokens,
        protected OracleFhirRequestConfigResolverInterface $resolver,
        array $overrides = []
    ) {
        parent::__construct($overrides);
    }

    protected function applyRequestOverrides(?string $clientId = null): void
    {
        $overrides = $this->resolver->resolveForRequest(request(), $clientId);
        if ($overrides !== []) {
            $this->initializeOracleConfig($overrides);
        }
    }

    /**
     * JWKS endpoint - returns public key in JWK format
     */
    public function jwks(?string $clientId = null): JsonResponse
    {
        $this->applyRequestOverrides($clientId);

        $publicKeyPem = file_get_contents($this->requirePublicKeyPath());
        if ($publicKeyPem === false) {
            return $this->error('Unable to read public key file', 500);
        }

        $jwk = $this->pemToJwk($publicKeyPem, $clientId ?? '');

        return $this->json([
            'keys' => [$jwk],
        ]);
    }

    // ────────────────────────────────────────────────
    //  SMART on FHIR related methods
    // ────────────────────────────────────────────────

    public function smartLaunch(string $clientId): RedirectResponse|JsonResponse
    {
        $this->applyRequestOverrides($clientId);

        $ownerKey = request()->query('ownerKey');
        if (! is_string($ownerKey) || $ownerKey === '') {
            return $this->error('Missing ownerKey. Pass ?ownerKey=... when starting SMART flow.', 400);
        }

        $host = strtolower(parse_url(request()->url(), PHP_URL_HOST));
        $allowedRoot = $this->oracleConfig('allowed_root');
        if ($host !== $allowedRoot && ! str_ends_with($host, '.'.$allowedRoot)) {
            return $this->error('Invalid host for redirect URI', 400);
        }

        $redirectUri = $this->smartRedirectUri($host);

        $state = bin2hex(random_bytes(16));

        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        $ttlSeconds = (int) $this->oracleConfig('smart_flow_ttl_seconds', 600);
        Cache::put($this->smartFlowCacheKey($state), [
            'client_id' => $clientId,
            'owner_key' => $ownerKey,
            'code_verifier' => $codeVerifier,
        ], now()->addSeconds(max(30, $ttlSeconds)));

        $params = [
            'client_id' => $clientId,
            'scope' => $this->oracleConfig('smart_scope'),
            'response_type' => 'code',
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'code_challenge_method' => $this->oracleConfig('code_challenge_method'),
            'code_challenge' => $codeChallenge,
            'aud' => $this->oracleConfig('fhir_base'),
        ];

        $authUrl = $this->oracleConfig('auth_url').'?'.http_build_query($params);

        return redirect()->away($authUrl);
    }

    public function smartCallback(): string|JsonResponse
    {
        $this->applyRequestOverrides();

        $code = request()->query('code');
        if (! $code) {
            return $this->error('No authorization code received', 400);
        }
        $state = request()->query('state');
        if (! is_string($state) || $state === '') {
            return $this->error('Invalid state', 400);
        }

        $flow = Cache::pull($this->smartFlowCacheKey($state));
        if (! is_array($flow)) {
            return $this->error('Invalid or expired state', 400);
        }

        $clientId = $flow['client_id'] ?? null;
        if (! is_string($clientId) || $clientId === '') {
            return $this->error('Invalid or expired state', 400);
        }

        $ownerKey = $flow['owner_key'] ?? null;
        if (! is_string($ownerKey) || $ownerKey === '') {
            return $this->error('Invalid or expired state', 400);
        }

        $codeVerifier = $flow['code_verifier'] ?? null;
        if (! is_string($codeVerifier) || $codeVerifier === '') {
            return $this->error('Invalid or expired state', 400);
        }

        $host = strtolower(parse_url(request()->url(), PHP_URL_HOST));
        $allowedRoot = $this->oracleConfig('allowed_root');
        if ($host !== $allowedRoot && ! str_ends_with($host, '.'.$allowedRoot)) {
            return $this->error('Invalid host for redirect URI', 400);
        }

        $redirectUri = $this->smartRedirectUri($host);

        try {
            $tokenData = $this->http->postForm($this->oracleConfig('token_url'), [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'code_verifier' => $codeVerifier,
                'aud' => $this->oracleConfig('fhir_base'),
            ]);
        } catch (\Throwable $e) {
            return $this->error('Token exchange failed: '.$e->getMessage(), 500);
        }

        $accessToken = $tokenData['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            return $this->error('No access_token received from token endpoint', 500);
        }

        $expiresIn = (int) ($tokenData['expires_in'] ?? 0);
        $expiresAt = $expiresIn > 0 ? (time() + max(0, $expiresIn)) : null;
        $ttl = is_int($expiresAt) ? max(0, $expiresAt - time()) : null;

        $this->tokens->put($clientId, 'smart', $ownerKey, [
            'access_token' => $accessToken,
            'refresh_token' => is_string($tokenData['refresh_token'] ?? null) ? $tokenData['refresh_token'] : null,
            'expires_at' => $expiresAt,
            'scope' => is_string($tokenData['scope'] ?? null) ? $tokenData['scope'] : null,
            'token_type' => is_string($tokenData['token_type'] ?? null) ? $tokenData['token_type'] : null,
            'patient_id' => is_string($tokenData['patient'] ?? null) ? $tokenData['patient'] : null,
        ], $ttl);

        return $this->smartPatientSummary($accessToken, is_string($tokenData['patient'] ?? null) ? $tokenData['patient'] : null);
    }

    private function smartFlowCacheKey(string $state): string
    {
        return self::SMART_FLOW_CACHE_PREFIX.$state;
    }

    public function smartPatientSummary(?string $accessToken = null, ?string $patientId = null): string|JsonResponse
    {
        if (! $accessToken || ! $patientId) {
            return $this->error('Missing access_token or patient_id', 400);
        }

        $url = $this->oracleConfig('fhir_base')."/Patient/{$patientId}/\$summary";

        try {
            $body = $this->http->getJson($url, [
                'Authorization' => "Bearer {$accessToken}",
                'Accept' => 'application/fhir+json',
            ]);
        } catch (\Throwable $e) {
            return $this->error('SMART Patient summary failed: '.$e->getMessage(), 500);
        }

        return json_encode($body);
    }

    private function smartRedirectUri(string $host): string
    {
        $baseUrl = $this->oracleConfig('base_url');
        if (is_string($baseUrl) && $baseUrl !== '') {
            $baseUrl = rtrim($baseUrl, '/');
            $prefix = trim((string) $this->oracleConfig('routes.prefix', 'fhir/R4'), '/');

            return $baseUrl.'/'.$prefix.'/smart/callback';
        }

        $scheme = request()->secure() ? 'https' : 'http';
        $prefix = trim((string) $this->oracleConfig('routes.prefix', 'fhir/R4'), '/');

        return "{$scheme}://{$host}/{$prefix}/smart/callback";
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }

    private function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }
}
