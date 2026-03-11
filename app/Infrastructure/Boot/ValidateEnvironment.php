<?php

declare(strict_types=1);

namespace App\Infrastructure\Boot;

/**
 * Boot-time environment / config validation.
 *
 * Ensures all critical configuration values are present and non-empty.
 * Uses config() instead of env() because Laravel intentionally returns
 * null from env() after config:cache. This is the correct approach.
 *
 * Called from AppServiceProvider::boot() to fail loudly during startup
 * rather than silently misbehaving at request time.
 *
 * Skipped in the 'testing' environment because phpunit.xml provides
 * its own forced env values.
 */
final class ValidateEnvironment
{
    /**
     * Map of human-readable label => config() key path.
     * These must all resolve to a non-empty value.
     *
     * @var array<string, string>
     */
    private const REQUIRED = [
        'APP_KEY'          => 'app.key',
        'APP_URL'          => 'app.url',
        'DB_CONNECTION'    => 'database.default',
        'DB_HOST'          => 'database.connections.pgsql.host',
        'DB_PORT'          => 'database.connections.pgsql.port',
        'DB_DATABASE'      => 'database.connections.pgsql.database',
        'DB_USERNAME'      => 'database.connections.pgsql.username',
        'REDIS_HOST'       => 'database.redis.default.host',
        'SESSION_DRIVER'   => 'session.driver',
        'QUEUE_CONNECTION' => 'queue.default',
        'CACHE_STORE'      => 'cache.default',
    ];

    /**
     * Required in production only (secrets that can be empty locally).
     *
     * @var array<string, string>
     */
    private const REQUIRED_IN_PRODUCTION = [
        'DB_PASSWORD'    => 'database.connections.pgsql.password',
        'REDIS_PASSWORD' => 'database.redis.default.password',
    ];

    /**
     * Validate all required configuration values.
     *
     * @throws \RuntimeException if any required config is missing or empty
     */
    public static function check(): void
    {
        // Skip in testing — phpunit.xml provides forced env values
        if (app()->environment('testing')) {
            return;
        }

        $missing = [];

        foreach (self::REQUIRED as $label => $configKey) {
            if (self::isEmptyConfig($configKey)) {
                $missing[] = $label;
            }
        }

        if (app()->isProduction()) {
            foreach (self::REQUIRED_IN_PRODUCTION as $label => $configKey) {
                if (self::isEmptyConfig($configKey)) {
                    $missing[] = $label;
                }
            }
        }

        if ($missing !== []) {
            $list = implode(', ', $missing);
            throw new \RuntimeException(
                "[Ogami ERP] Missing required environment variable(s): {$list}. "
                .'Check your .env file or environment configuration.'
            );
        }
    }

    private static function isEmptyConfig(string $configKey): bool
    {
        $value = config($configKey);

        return $value === null || $value === '';
    }
}
