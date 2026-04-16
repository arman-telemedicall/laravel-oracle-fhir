<?php

namespace Teleminergmbh\OracleFhir\Services;

use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirAuthServiceInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirHttpClientInterface;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirTokenStoreInterface;
use Teleminergmbh\OracleFhir\Database\Traits\OracleConfigTrait;

class OracleFhirAuthService implements OracleFhirAuthServiceInterface
{
    use OracleConfigTrait;

    public function __construct(
        protected OracleFhirHttpClientInterface $http,
        protected OracleFhirTokenStoreInterface $tokens,
        array $overrides = [],
    ) {
        $this->initializeOracleConfig($overrides);
    }

    public function getSystemAccessToken(string $clientId, string $tenantId): string
    {
        $ownerKey = (string) $this->oracleConfig('connection_key', 'default');
        $data = $this->tokens->get($clientId, $tenantId, 'system', $ownerKey);

        $accessToken = $data['access_token'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;

        if (is_string($accessToken) && $accessToken !== '' && is_int($expiresAt) && $expiresAt > time()) {
            return $accessToken;
        }

        return $this->requestClientCredentialsToken($clientId, $tenantId, $ownerKey);
    }

    public function getSmartAccessToken(string $clientId, string $tenantId, string $ownerKey): string
    {
        $data = $this->tokens->get($clientId, $tenantId, 'smart', $ownerKey);

        $accessToken = $data['access_token'] ?? null;
        $expiresAt = $data['expires_at'] ?? null;

        if (is_string($accessToken) && $accessToken !== '' && is_int($expiresAt) && $expiresAt > time()) {
            return $accessToken;
        }

        $refreshToken = $data['refresh_token'] ?? null;
        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException('SMART access token expired or missing and no refresh_token is available for ownerKey: '.$ownerKey);
        }

        $lockKey = $this->lockKey($clientId, $tenantId, 'smart', $ownerKey);
        $lockSeconds = (int) $this->oracleConfig('token_store.cache.lock_seconds', 10);

        $lock = Cache::lock($lockKey, $lockSeconds);

        return $lock->block($lockSeconds, function () use ($clientId, $tenantId, $ownerKey) {
            $latest = $this->tokens->get($clientId, $tenantId, 'smart', $ownerKey);

            $latestAccessToken = $latest['access_token'] ?? null;
            $latestExpiresAt = $latest['expires_at'] ?? null;

            if (is_string($latestAccessToken) && $latestAccessToken !== '' && is_int($latestExpiresAt) && $latestExpiresAt > time()) {
                return $latestAccessToken;
            }

            $latestRefreshToken = $latest['refresh_token'] ?? null;
            if (! is_string($latestRefreshToken) || $latestRefreshToken === '') {
                throw new RuntimeException('No refresh_token available to refresh SMART access token for ownerKey: '.$ownerKey);
            }

            return $this->refreshSmartToken($clientId, $tenantId, $ownerKey, $latestRefreshToken, $latest);
        });
    }

    protected function refreshSmartToken(string $clientId, string $tenantId, string $ownerKey, string $refreshToken, array $existingData): string
    {
		$url = $this->oracleConfig('auth_url') . $tenantId .'/.well-known/smart-configuration';
		if($this->oracleConfig('sandbox_enabled')){$url = 'https://fhir-ehr.cerner.com/r4/e573b5d9-449b-411c-b6af-73f7fedafc83/.well-known/smart-configuration';}
		$wellKnown = $this->http->getJson( $url, [] );
        try {
            $tokenData = $this->http->postForm($wellKnown['authorization_endpoint'], [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Refresh token request failed: '.$e->getMessage(), 0, $e);
        }

        $accessToken = $tokenData['access_token'] ?? null;
        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('No access_token received from refresh_token grant');
        }

        $expiresIn = (int) ($tokenData['expires_in'] ?? 0);
        $expiresAt = $expiresIn > 0 ? $this->expiresAtFromExpiresIn($expiresIn) : null;
        $ttl = is_int($expiresAt) ? $this->ttlFromExpiresAt($expiresAt) : null;

        $newRefreshToken = is_string($tokenData['refresh_token'] ?? null) ? $tokenData['refresh_token'] : null;

        $merged = array_merge($existingData, [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
            'scope' => is_string($tokenData['scope'] ?? null) ? $tokenData['scope'] : ($existingData['scope'] ?? null),
            'token_type' => is_string($tokenData['token_type'] ?? null) ? $tokenData['token_type'] : ($existingData['token_type'] ?? null),
        ]);

        if ($newRefreshToken) {
            $merged['refresh_token'] = $newRefreshToken;
        }

        $this->tokens->put($clientId, $tenantId, 'smart', $ownerKey, $merged, $ttl);

        return $accessToken;
    }

    protected function requestClientCredentialsToken(string $clientId, string $tenantId, string $ownerKey): string
    {
		$url = $this->oracleConfig('token_url') . $tenantId .'/.well-known/smart-configuration';
		if($this->oracleConfig('sandbox_enabled')){$url = 'https://fhir-ehr.cerner.com/r4/e573b5d9-449b-411c-b6af-73f7fedafc83/.well-known/smart-configuration';}
		$wellKnown = $this->http->getJson( $url, [] );
		
        $privateKey = file_get_contents($this->requirePrivateKeyPath());

        if ($privateKey === false) {
            throw new RuntimeException('Could not read private key');
        }

        $kid = (string) $this->oracleConfig('jwt_kid', '');
        if ($kid === '') {
            $publicKeyPem = file_get_contents($this->requirePublicKeyPath());
            if ($publicKeyPem === false) {
                throw new RuntimeException('Could not read public key');
            }

            $kid = $this->base64UrlEncode(hash('sha256', $publicKeyPem, true));
        }

        $header = [
            'alg' => $this->oracleConfig('jwt_alg'),
            'typ' => 'JWT',
            'kid' => $kid,
        ];

        $now = time();
        $claims = [
            'iss' => $clientId,
            'sub' => $clientId,
            'aud' => $wellKnown['token_endpoint'],
            'jti' => bin2hex(random_bytes(16)),
            'iat' => $now,
            'exp' => $now + $this->oracleConfig('jwt_exp_seconds', 300),
        ];

        $jwtHeader = $this->base64UrlEncode(json_encode($header));
        $jwtClaims = $this->base64UrlEncode(json_encode($claims));
        $unsignedJwt = $jwtHeader.'.'.$jwtClaims;

        $signature = '';
        $success = openssl_sign(
            $unsignedJwt,
            $signature,
            $privateKey,
            match ((string) $this->oracleConfig('jwt_alg', 'RS256')) {
                'RS384' => OPENSSL_ALGO_SHA384,
                'RS512' => OPENSSL_ALGO_SHA512,
                default => OPENSSL_ALGO_SHA256,
            }
        );

        if (! $success) {
            throw new RuntimeException('Failed to sign JWT assertion');
        }

        $jwtAssertion = $unsignedJwt.'.'.$this->base64UrlEncode($signature);

        try {
            $tokenData = $this->http->postForm($wellKnown['token_endpoint'], [
                'grant_type' => 'client_credentials',
                'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer',
                'client_assertion' => $jwtAssertion,
                'scope' => $this->oracleConfig('oauth_scope'),
            ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Token request failed: '.$e->getMessage(), 0, $e);
        }
        $accessToken = $tokenData['access_token'] ?? null;

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('No access token received');
        }

        $expiresIn = (int) ($tokenData['expires_in'] ?? $this->oracleConfig('jwt_exp_seconds', 300));
        $expiresAt = $this->expiresAtFromExpiresIn($expiresIn);
        $ttl = $this->ttlFromExpiresAt($expiresAt);

        $this->tokens->put($clientId, $tenantId, 'system', $ownerKey, [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
            'scope' => is_string($tokenData['scope'] ?? null) ? $tokenData['scope'] : null,
            'token_type' => is_string($tokenData['token_type'] ?? null) ? $tokenData['token_type'] : null,
        ], $ttl);

        return $accessToken;
    }

    protected function expiresAtFromExpiresIn(int $expiresIn): int
    {
        $buffer = (int) $this->oracleConfig('token_store.cache.expires_buffer_seconds', 60);

        return time() + max(0, $expiresIn - $buffer);
    }

    protected function ttlFromExpiresAt(int $expiresAt): int
    {
        return max(0, $expiresAt - time());
    }

    protected function lockKey(string $clientId, string $tenantId, string $flow, string $ownerKey): string
    {
        $prefix = (string) $this->oracleConfig('token_store.cache.prefix', 'oracle_fhir');

        return $prefix.':'.$clientId.':'.$tenantId.':'.$flow.':'.$ownerKey.':lock';
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
