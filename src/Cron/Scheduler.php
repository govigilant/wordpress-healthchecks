<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Cron;

use Vigilant\HealthChecksBase\Checks\Check as BaseCheck;
use Vigilant\HealthChecksBase\Checks\Metric as BaseMetric;
use Vigilant\WordpressHealthchecks\HealthCheckRegistry;
use Vigilant\WordpressHealthchecks\Settings\Options;
use function __;
use function add_action;
use function add_filter;
use function register_activation_hook;
use function register_deactivation_hook;
use function time;
use function update_option;
use function wp_clear_scheduled_hook;
use function wp_next_scheduled;
use function wp_schedule_event;

class Scheduler
{
    public static function boot(string $pluginFile): void
    {
        register_activation_hook($pluginFile, [self::class, 'activate']);
        register_deactivation_hook($pluginFile, [self::class, 'deactivate']);

        add_filter('cron_schedules', [self::class, 'registerSchedule']);
        add_action('init', [self::class, 'scheduleEvent']);
        add_action(VIGILANT_HEALTH_CRON_HOOK, [self::class, 'recordCronRun']);
        add_action('vigilant_healthchecks_prepare', [self::class, 'registerChecksAndMetrics']);
    }

    public static function activate(): void
    {
        self::scheduleEvent();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(VIGILANT_HEALTH_CRON_HOOK);
    }

    /**
     * @param array<string, array{interval: int, display: string}> $schedules
     * @return array<string, array{interval: int, display: string}>
     */
    public static function registerSchedule(array $schedules): array
    {
        $schedules['vigilant_healthchecks_minutely'] = [
            'interval' => 60,
            'display' => __('Every minute', 'vigilant-healthchecks'),
        ];

        return $schedules;
    }

    public static function scheduleEvent(): void
    {
        if (! wp_next_scheduled(VIGILANT_HEALTH_CRON_HOOK)) {
            wp_schedule_event(time(), 'vigilant_healthchecks_minutely', VIGILANT_HEALTH_CRON_HOOK);
        }
    }

    public static function recordCronRun(): void
    {
        update_option(VIGILANT_HEALTH_LAST_CRON_OPTION, time(), false);
    }

    public static function registerChecksAndMetrics(HealthCheckRegistry $registry): void
    {
        $availableChecks = Options::availableChecks();

        foreach (Options::enabledChecks() as $key => $enabled) {
            if (! $enabled || ! isset($availableChecks[$key])) {
                continue;
            }

            /** @var class-string<BaseCheck> $class */
            $class = $availableChecks[$key]['class'];
            $registry->registerCheck($class::make());
        }

        $availableMetrics = Options::availableMetrics();

        foreach (Options::enabledMetrics() as $key => $enabled) {
            if (! $enabled || ! isset($availableMetrics[$key])) {
                continue;
            }

            /** @var class-string<BaseMetric> $class */
            $class = $availableMetrics[$key]['class'];
            $registry->registerMetric($class::make());
        }
    }
}
