<?php

namespace Teleminergmbh\OracleFhir\Controllers;

use Illuminate\Http\JsonResponse;
use RuntimeException;
use Teleminergmbh\OracleFhir\Database\Traits\OracleConfigTrait;

abstract class BaseController
{
    use OracleConfigTrait;

    public function __construct(array $overrides = [])
    {
        $this->initializeOracleConfig($overrides);
    }

    protected function json(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status);
    }

    protected function error(string $message, int $status = 400): JsonResponse
    {
        return $this->json(['error' => $message], $status);
    }

    protected function pemToJwk(string $publicKeyPem, string $clientId): array
    {
        $keyResource = openssl_pkey_get_public($publicKeyPem);
        if ($keyResource === false) {
            throw new RuntimeException('Invalid public key');
        }

        $details = openssl_pkey_get_details($keyResource);
        if (! $details || ! isset($details['rsa'])) {
            throw new RuntimeException('Failed to extract RSA key details');
        }

        $kid = (string) $this->oracleConfig('jwt_kid', '');
        if ($kid === '') {
            $kid = $this->kidFromPublicKey($publicKeyPem);
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'alg' => $this->oracleConfig('jwt_alg'),
            'kid' => $kid,
            'n' => $this->base64UrlEncode($details['rsa']['n']),
            'e' => $this->base64UrlEncode($details['rsa']['e']),
        ];
    }

    protected function kidFromPublicKey(string $publicKeyPem): string
    {
        return $this->base64UrlEncode(hash('sha256', $publicKeyPem, true));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
