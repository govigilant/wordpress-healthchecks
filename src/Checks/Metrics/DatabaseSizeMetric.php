<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks\Metrics;

use RuntimeException;
use Vigilant\HealthChecksBase\Checks\Metric;
use Vigilant\HealthChecksBase\Data\MetricData;
use wpdb;
use function apply_filters;
use function do_action;
use function function_exists;
use function is_array;
use function max;
use function round;
use function time;
use function wp_cache_get;
use function wp_cache_set;

class DatabaseSizeMetric extends Metric
{
    protected string $type = 'database_size';

    private const CACHE_GROUP = 'vigilant_healthchecks_database_size';

    /** @var array<string, array{value: float, expires_at: int}> */
    private static array $sizeCache = [];

    public function measure(): MetricData
    {
        $meta = [];

        try {
            $wpdb = $this->resolveWpdb();
            $sizeBytes = $this->calculateSize($wpdb);
        } catch (RuntimeException $exception) {
            $sizeBytes = 0.0;
            $meta['error'] = 'Unable to calculate database size.';
            $this->logException($exception);
        }

        $data = [
            'type' => $this->type(),
            'value' => round($sizeBytes / (1024 * 1024), 2),
            'unit' => 'MB',
        ];

        if ($meta !== []) {
            $data['meta'] = $meta;
        }

        return MetricData::make($data);
    }

    public function available(): bool
    {
        try {
            $this->resolveWpdb();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function resolveWpdb(): wpdb
    {
        global $wpdb;

        if ($wpdb instanceof wpdb) {
            return $wpdb;
        }

        throw new RuntimeException('WordPress database object not available.');
    }

    private function calculateSize(wpdb $wpdb): float
    {
        $cacheKey = $this->cacheKey($wpdb);
        $cached = $this->getCachedSize($cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $prefix = (string) $wpdb->prefix;

        if ($prefix === '') {
            throw new RuntimeException('WordPress table prefix is not defined.');
        }

        $like = $wpdb->esc_like($prefix).'%';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- SHOW TABLE STATUS requires direct DB access; results are cached via cacheTtl().
        $tables = $wpdb->get_results(
            $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $like)
        );

        if (! is_array($tables)) {
            throw new RuntimeException('SHOW TABLE STATUS returned an unexpected response.');
        }

        $total = 0.0;

        foreach ($tables as $table) {
            $dataLength = (float) ($table->Data_length ?? 0);
            $indexLength = (float) ($table->Index_length ?? 0);
            $total += $dataLength + $indexLength;
        }

        $this->setCachedSize($cacheKey, $total);

        return $total;
    }

    private function cacheKey(wpdb $wpdb): string
    {
        return (string) $wpdb->prefix;
    }

    private function getCachedSize(string $key): ?float
    {
        $external = $this->getExternalCache($key);

        if ($external !== null) {
            return $external;
        }

        $cache = self::$sizeCache[$key] ?? null;

        if ($cache === null) {
            return null;
        }

        if ($cache['expires_at'] < time()) {
            unset(self::$sizeCache[$key]);

            return null;
        }

        return $cache['value'];
    }

    private function getExternalCache(string $key): ?float
    {
        if (! function_exists('wp_cache_get')) {
            return null;
        }

        $cached = wp_cache_get($key, self::CACHE_GROUP);

        if ($cached === false) {
            return null;
        }

        return (float) $cached;
    }

    private function setCachedSize(string $key, float $value): void
    {
        $ttl = $this->cacheTtl();

        if ($ttl <= 0) {
            return;
        }

        self::$sizeCache[$key] = [
            'value' => $value,
            'expires_at' => time() + $ttl,
        ];

        $this->setExternalCache($key, $value, $ttl);
    }

    private function setExternalCache(string $key, float $value, int $ttl): void
    {
        if (! function_exists('wp_cache_set')) {
            return;
        }

        wp_cache_set($key, $value, self::CACHE_GROUP, $ttl);
    }

    private function cacheTtl(): int
    {
        $default = 300;

        if (function_exists('apply_filters')) {
            $default = (int) apply_filters('vigilant_healthchecks_database_size_cache_ttl', $default);
        }

        return max(0, $default);
    }

    private function logException(RuntimeException $exception): void
    {
        if (! function_exists('do_action')) {
            return;
        }

        do_action('vigilant_healthchecks_metric_exception', $this->type(), $exception);
    }
}
