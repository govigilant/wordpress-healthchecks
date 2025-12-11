<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks;

use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use function apply_filters;
use function get_option;
use function time;

class CronCheck extends Check
{
    protected string $type = 'wordpress_cron';

    public function run(): ResultData
    {
        if ($this->isDisabled()) {
            return $this->buildResult(
                Status::Warning,
                'WP-Cron is disabled via DISABLE_WP_CRON.',
                null,
                null,
                null
            );
        }

        $lastRun = (int) get_option(VIGILANT_HEALTH_LAST_CRON_OPTION, 0);
        $threshold = (int) apply_filters('vigilant_healthchecks_cron_threshold', 5 * MINUTE_IN_SECONDS);

        if ($lastRun === 0) {
            return $this->buildResult(
                Status::Warning,
                'Cron monitor has not run yet.',
                $lastRun,
                $threshold,
                null
            );
        }

        $diff = time() - $lastRun;
        $isHealthy = $diff <= $threshold;

        $message = $isHealthy
            ? 'WP-Cron has run within the expected timeframe.'
            : sprintf('WP-Cron has not run for %d seconds.', $diff);

        return $this->buildResult(
            $isHealthy ? Status::Healthy : Status::Warning,
            $message,
            $lastRun,
            $threshold,
            $diff
        );
    }

    public function available(): bool
    {
        return ! $this->isDisabled();
    }

    private function isDisabled(): bool
    {
        return defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
    }

    private function buildResult(Status $status, string $message, ?int $lastRun, ?int $threshold = null, ?int $elapsed = null): ResultData
    {
        $data = [];

        if ($lastRun !== null) {
            $data['last_run'] = $lastRun;
        }

        if ($threshold !== null) {
            $data['threshold_seconds'] = $threshold;
        }

        if ($elapsed !== null) {
            $data['elapsed_seconds'] = $elapsed;
        }

        return ResultData::make([
            'type' => $this->type(),
            'key' => null,
            'status' => $status,
            'message' => $message,
            'data' => $data ?: null,
        ]);
    }
}
