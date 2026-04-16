<?php

namespace Teleminergmbh\OracleFhir\Database\Traits;

use Illuminate\Support\Arr;

trait OracleConfigTrait
{
    /**
     * The merged configuration array.
     *
     * @var array<string, mixed>
     */
    protected array $oracleConfig = [];

    /**
     * Merge package defaults with Laravel config + optional runtime overrides.
     */
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public function initializeOracleConfig(array $overrides = []): array
    {
        // Start with Laravel config (published config/oracle-fhir.php)
        $config = config('laravel-oracle-fhir', []);

        // Merge defaults → published config → runtime overrides
        $merged = array_replace_recursive(
            $config,
            $overrides
        );

        $this->oracleConfig = $merged;

        return $this->oracleConfig;
    }

    /**
     * Get a config value using dot notation.
     *
     * @param  mixed  $default
     * @return mixed
     */
    public function oracleConfig(string $key, $default = null)
    {
        return Arr::get($this->oracleConfig, $key, $default);
    }

    /**
     * Set a config value at runtime (useful for testing or dynamic changes).
     *
     * @param  mixed  $value
     */
    public function setOracleConfig(string $key, $value): void
    {
        Arr::set($this->oracleConfig, $key, $value);
    }

    /**
     * Get the full config array.
     *
     * @return array<string, mixed>
     */
    public function allOracleConfig(): array
    {
        return $this->oracleConfig;
    }

    public function requirePrivateKeyPath(): string
    {
        return $this->resolveKeyPath($this->oracleConfig('private_key_path'));
    }

    public function requirePublicKeyPath(): string
    {
        return $this->resolveKeyPath($this->oracleConfig('public_key_path'));
    }

    /**
     * Resolve and validate key file path.
     *
     * @throws \RuntimeException
     */
    protected function resolveKeyPath(?string $path): string
    {
        if (empty($path) || ! file_exists($path) || ! is_readable($path)) {
            throw new \RuntimeException(
                'Oracle FHIR key file not found or not readable: '.($path ?? 'null')
            );
        }

        return $path;
    }
}
