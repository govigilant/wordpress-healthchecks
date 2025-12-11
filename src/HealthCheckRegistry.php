<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks;

use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Checks\Metric;

class HealthCheckRegistry
{
    /** @var array<int, Check> */
    protected array $checks = [];

    /** @var array<int, Metric> */
    protected array $metrics = [];

    public function registerCheck(Check $check): void
    {
        $this->checks[] = $check;
    }

    public function registerMetric(Metric $metric): void
    {
        $this->metrics[] = $metric;
    }

    /**
     * @return array<int, Check>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    /**
     * @return array<int, Metric>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    public function clear(): void
    {
        $this->checks = [];
        $this->metrics = [];
    }
}
