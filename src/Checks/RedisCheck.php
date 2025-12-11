<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks;

use Redis;
use RuntimeException;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use function error_log;

class RedisCheck extends Check
{
    protected string $type = 'redis_connection';

    public function run(): ResultData
    {
        try {
            $settings = $this->resolveSettings();
            $redis = $this->createClient();

            $redis->connect($settings['host'], $settings['port'], $settings['timeout']);

            if ($settings['password'] !== null) {
                $redis->auth($settings['password']);
            }

            if ($settings['database'] !== null) {
                $redis->select($settings['database']);
            }

            $ping = $redis->ping();
            $isHealthy = $ping === '+PONG' || $ping === 'PONG' || $ping === true;
            $message = $isHealthy
                ? sprintf('Redis connection is healthy (%s:%d).', $settings['host'], $settings['port'])
                : sprintf('Redis ping failed for %s:%d.', $settings['host'], $settings['port']);
        } catch (Throwable $exception) {
            $isHealthy = false;
            $message = 'Redis connection failed.';
            error_log(sprintf('[Vigilant Healthchecks] Redis check failed: %s', $exception->getMessage()));
        }

        return ResultData::make([
            'type' => $this->type(),
            'key' => null,
            'status' => $isHealthy ? Status::Healthy : Status::Unhealthy,
            'message' => $message,
        ]);
    }

    public function available(): bool
    {
        return class_exists(Redis::class);
    }

    /**
     * @return array{host: string, port: int, timeout: float, password: ?string, database: ?int}
     */
    private function resolveSettings(): array
    {
        if (! $this->available()) {
            throw new RuntimeException('PHP Redis extension is not installed.');
        }

        $host = defined('WP_REDIS_HOST') ? (string) WP_REDIS_HOST : '127.0.0.1';
        $port = defined('WP_REDIS_PORT') ? (int) WP_REDIS_PORT : 6379;
        $timeout = defined('WP_REDIS_TIMEOUT') ? (float) WP_REDIS_TIMEOUT : 1.5;
        $password = defined('WP_REDIS_PASSWORD') ? (string) WP_REDIS_PASSWORD : null;
        $database = defined('WP_REDIS_DATABASE') ? (int) WP_REDIS_DATABASE : null;

        return compact('host', 'port', 'timeout', 'password', 'database');
    }

    private function createClient(): Redis
    {
        if (! $this->available()) {
            throw new RuntimeException('PHP Redis extension is not installed.');
        }

        return new Redis();
    }
}
