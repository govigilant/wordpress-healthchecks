<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks\Metrics;

use RuntimeException;
use Vigilant\HealthChecksBase\Checks\Metric;
use Vigilant\HealthChecksBase\Data\MetricData;
use wpdb;
use function apply_filters;
use function error_log;
use function function_exists;
use function is_array;
use function max;
use function round;
use function sprintf;
use function time;

class DatabaseSizeMetric extends Metric
{
    protected string $type = 'database_size';

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
        $query = $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $like);
        $tables = $wpdb->get_results($query);

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
        error_log(sprintf('[Vigilant Healthchecks] Database size metric failed: %s', $exception->getMessage()));
    }
}
