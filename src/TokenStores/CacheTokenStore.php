<?php

namespace Teleminergmbh\OracleFhir\TokenStores;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Teleminergmbh\OracleFhir\Contracts\OracleFhirTokenStoreInterface;

class CacheTokenStore implements OracleFhirTokenStoreInterface
{
    public function __construct(
        protected string $prefix = 'oracle_fhir',
        protected ?string $store = null,
    ) {}

    public function get(string $clientId, string $tenantId, string $flow, string $ownerKey): array
    {
        $value = $this->cache()->get($this->key($clientId, $tenantId, $flow, $ownerKey));

        return is_array($value) ? $value : [];
    }

    public function put(string $clientId, string $tenantId, string $flow, string $ownerKey, array $data, ?int $ttlSeconds = null): void
    {
        $key = $this->key($clientId, $tenantId, $flow, $ownerKey);

        if ($ttlSeconds !== null) {
            $this->cache()->put($key, $data, $ttlSeconds);

            return;
        }

        $this->cache()->forever($key, $data);
    }

    public function forget(string $clientId, string $tenantId, string $flow, string $ownerKey): void
    {
        $this->cache()->forget($this->key($clientId, $tenantId, $flow, $ownerKey));
    }

    protected function key(string $clientId, string $tenantId, string $flow, string $ownerKey): string
    {
        return $this->prefix.':'.$clientId.':'.$tenantId.':'.$flow.':'.$ownerKey;
    }

    protected function cache(): Repository
    {
        return $this->store ? Cache::store($this->store) : Cache::store();
    }
}
