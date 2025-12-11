<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Settings;

use Vigilant\HealthChecksBase\Checks\Metrics\CpuLoadMetric;
use Vigilant\HealthChecksBase\Checks\Metrics\DiskUsageMetric;
use Vigilant\HealthChecksBase\Checks\Metrics\MemoryUsageMetric;
use Vigilant\WordpressHealthchecks\Checks\CoreVersionCheck;
use Vigilant\WordpressHealthchecks\Checks\CronCheck;
use Vigilant\WordpressHealthchecks\Checks\DatabaseCheck;
use Vigilant\WordpressHealthchecks\Checks\Metrics\DatabaseSizeMetric;
use Vigilant\WordpressHealthchecks\Checks\PluginUpdatesCheck;
use Vigilant\WordpressHealthchecks\Checks\RedisCheck;
use Vigilant\WordpressHealthchecks\Checks\SiteHealthCheck;
use Vigilant\WordpressHealthchecks\HealthCheckRegistry;
use function __;
use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function ctype_xdigit;
use function get_option;
use function hash;
use function is_array;
use function is_string;
use function sanitize_text_field;
use function strlen;
use function trim;

class Options
{
    private const TOKEN_PLACEHOLDER = '__VIGILANT_HEALTHCHECKS_TOKEN__';

    /**
     * @return array<string, array{label: string, class: class-string<\Vigilant\HealthChecksBase\Checks\Check>}>
     */
    public static function availableChecks(): array
    {
        return [
            'database' => [
                'label' => __('Database connection', 'vigilant-healthchecks'),
                'class' => DatabaseCheck::class,
            ],
            'site_health' => [
                'label' => __('Site Health', 'vigilant-healthchecks'),
                'class' => SiteHealthCheck::class,
            ],
            'core_version' => [
                'label' => __('Core version', 'vigilant-healthchecks'),
                'class' => CoreVersionCheck::class,
            ],
            'redis' => [
                'label' => __('Redis', 'vigilant-healthchecks'),
                'class' => RedisCheck::class,
            ],
            'plugin_updates' => [
                'label' => __('Plugin updates', 'vigilant-healthchecks'),
                'class' => PluginUpdatesCheck::class,
            ],
            'cron' => [
                'label' => __('WP Cron', 'vigilant-healthchecks'),
                'class' => CronCheck::class,
            ],
        ];
    }

    /**
     * @return array<string, array{label: string, class: class-string<\Vigilant\HealthChecksBase\Checks\Metric>}>
     */
    public static function availableMetrics(): array
    {
        return [
            'memory_usage' => [
                'label' => __('Memory usage', 'vigilant-healthchecks'),
                'class' => MemoryUsageMetric::class,
            ],
            'disk_usage' => [
                'label' => __('Disk usage', 'vigilant-healthchecks'),
                'class' => DiskUsageMetric::class,
            ],
            'cpu_load' => [
                'label' => __('CPU load', 'vigilant-healthchecks'),
                'class' => CpuLoadMetric::class,
            ],
            'database_size' => [
                'label' => __('Database size', 'vigilant-healthchecks'),
                'class' => DatabaseSizeMetric::class,
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultCheckToggles(): array
    {
        return array_fill_keys(array_keys(self::availableChecks()), true);
    }

    /**
     * @return array<string, bool>
     */
    public static function defaultMetricToggles(): array
    {
        return array_fill_keys(array_keys(self::availableMetrics()), true);
    }

    /**
     * @return array<string, bool>
     */
    public static function enabledChecks(): array
    {
        return self::normalizeToggleValues(
            get_option(VIGILANT_HEALTH_OPTION_CHECKS, null),
            self::defaultCheckToggles()
        );
    }

    /**
     * @return array<string, bool>
     */
    public static function enabledMetrics(): array
    {
        return self::normalizeToggleValues(
            get_option(VIGILANT_HEALTH_OPTION_METRICS, null),
            self::defaultMetricToggles()
        );
    }

    /**
     * @param array<string, mixed>|null $value
     * @return array<string, bool>
     */
    public static function sanitizeCheckToggles($value): array
    {
        return self::sanitizeToggleOption($value, self::defaultCheckToggles());
    }

    /**
     * @param array<string, mixed>|null $value
     * @return array<string, bool>
     */
    public static function sanitizeMetricToggles($value): array
    {
        return self::sanitizeToggleOption($value, self::defaultMetricToggles());
    }

    public static function getApiToken(): string
    {
        return trim((string) get_option(VIGILANT_HEALTH_OPTION_TOKEN, ''));
    }

    public static function hasApiToken(): bool
    {
        return self::getApiTokenDigest() !== '';
    }

    public static function tokenPlaceholderValue(): string
    {
        return self::TOKEN_PLACEHOLDER;
    }

    public static function getApiTokenDigest(): string
    {
        $token = self::getApiToken();

        return self::isHashedToken($token) ? $token : '';
    }

    public static function sanitizeApiToken(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = trim(sanitize_text_field($value));

        if ($value === '') {
            return '';
        }

        if ($value === self::TOKEN_PLACEHOLDER) {
            return self::getApiToken();
        }

        return hash('sha256', $value);
    }

    private static function isHashedToken(string $token): bool
    {
        return strlen($token) === 64 && ctype_xdigit($token);
    }

    public static function registry(): HealthCheckRegistry
    {
        return new HealthCheckRegistry();
    }

    /**
     * @param array<string, mixed>|null $values
     * @param array<string, bool> $defaults
     * @return array<string, bool>
     */
    private static function normalizeToggleValues($values, array $defaults): array
    {
        if (! is_array($values)) {
            $values = [];
        }

        $normalized = [];

        foreach ($defaults as $key => $default) {
            $normalized[$key] = array_key_exists($key, $values)
                ? (bool) $values[$key]
                : (bool) $default;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|null $value
     * @param array<string, bool> $defaults
     * @return array<string, bool>
     */
    private static function sanitizeToggleOption($value, array $defaults): array
    {
        $value = is_array($value) ? $value : [];

        $sanitized = [];

        foreach (array_keys($defaults) as $key) {
            $sanitized[$key] = ! empty($value[$key]);
        }

        return $sanitized;
    }
}
