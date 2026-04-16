<?php

namespace Teleminergmbh\OracleFhir\Http;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirHttpClientInterface;

class OracleFhirHttpClient implements OracleFhirHttpClientInterface
{
    /**
     * @param  array<string, string>  $headers
     */
    protected function request(array $headers = []): PendingRequest
    {
        return Http::withHeaders($headers);
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function getJson(string $url, array $headers = []): array
    {
        $response = $this->request($headers)->get($url);
        $response->throw();

        return (array) $response->json();
    }

    /**
     * @param  array<string, scalar|null>  $query
     * @param  array<string, string>  $headers
     */
    public function getRaw(string $url, array $query = [], array $headers = []): string
    {
        $response = $this->request($headers)->get($url, $query);
        $response->throw();

        return (string) $response->body();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function postJson(string $url, array $payload, array $headers = []): array
    {
        $response = $this->request($headers)->post($url, $payload);
        $response->throw();

        return (array) $response->json();
    }

    /**
     * @param  array<string, scalar|null>  $payload
     * @param  array<string, string>  $headers
     */
    public function postForm(string $url, array $payload, array $headers = []): array
    {
        $response = $this->request($headers)->asForm()->post($url, $payload);
        $response->throw();

        return (array) $response->json();
    }
}
