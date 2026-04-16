<?php

namespace Teleminergmbh\OracleFhir\Contracts;

interface OracleFhirHttpClientInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getJson(string $url, array $headers = []): array;

    /**
     * @param  array<string, scalar|null>  $query
     */
    public function getRaw(string $url, array $query = [], array $headers = []): string;

    /**
     * @return array<string, mixed>
     */
    public function postJson(string $url, array $payload, array $headers = []): array;

    /**
     * @param  array<string, scalar|null>  $payload
     * @return array<string, mixed>
     */
    public function postForm(string $url, array $payload, array $headers = []): array;
}
