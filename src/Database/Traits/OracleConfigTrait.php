<?php

namespace Telemedicall\OracleFhir\Database\Traits;

use Illuminate\Support\Arr;

trait OracleConfigTrait
{
    /**
     * The merged configuration array.
     *
     * @var array
     */
    protected array $OracleConfig = [];

    /**
     * Merge package defaults with Laravel config + optional runtime overrides.
     *
     * @param  array  $overrides
     * @return array
     */
    public function initializeOracleConfig(array $overrides = []): array
    {
        // Start with Laravel config (published config/Oracle-fhir.php)
        $config = config('OracleFhir', []);

        // Merge defaults → published config → runtime overrides
        $merged = array_replace_recursive(
            $config,
            $overrides
        );

        // Ensure key paths are resolved (prevent null or empty breaking openssl)
        $merged['private_key_path'] = $this->resolveKeyPath($merged['private_key_path']);

        $merged['public_key_path'] = $this->resolveKeyPath($merged['public_key_path']);

        $this->OracleConfig = $merged;

        return $this->OracleConfig;
    }

    /**
     * Get a config value using dot notation.
     *
     * @param  string  $key
     * @param  mixed   $default
     * @return mixed
     */
    public function OracleConfig(string $key, $default = null)
    {
        return Arr::get($this->OracleConfig, $key, $default);
    }

    /**
     * Set a config value at runtime (useful for testing or dynamic changes).
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setOracleConfig(string $key, $value): void
    {
        Arr::set($this->OracleConfig, $key, $value);
    }

    /**
     * Get the full config array.
     *
     * @return array
     */
    public function allOracleConfig(): array
    {
        return $this->OracleConfig;
    }

    /**
     * Resolve and validate key file path.
     *
     * @param  string|null  $path
     * @return string
     * @throws \RuntimeException
     */
    protected function resolveKeyPath(?string $path): string
    {
        if (empty($path) || !file_exists($path) || !is_readable($path)) {
            throw new \RuntimeException(
                "Oracle FHIR key file not found or not readable: " . ($path ?? 'null')
            );
        }

        return $path;
    }
}