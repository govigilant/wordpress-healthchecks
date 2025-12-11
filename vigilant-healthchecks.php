<?php

/**
 * Plugin Name: Vigilant Healthchecks
 * Description: A WordPress plugin that provides healthchecks to your WordPress site that integrate seamlessly with Vigilant (https://govigilant.io).
 * Version: 1.0.0
 * Author: Vincent Bean
 * License: MIT
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use Vigilant\WordpressHealthchecks\Admin\SettingsPage;
use Vigilant\WordpressHealthchecks\Cron\Scheduler;
use Vigilant\WordpressHealthchecks\Rest\HealthEndpoint;

const VIGILANT_HEALTH_REST_NAMESPACE = 'vigilant/v1';
const VIGILANT_HEALTH_REST_ROUTE = '/health';
const VIGILANT_HEALTH_CRON_HOOK = 'vigilant_healthchecks_cron_monitor';
const VIGILANT_HEALTH_LAST_CRON_OPTION = 'vigilant_healthchecks_last_cron_run';
const VIGILANT_HEALTH_OPTION_CHECKS = 'vigilant_healthchecks_enabled_checks';
const VIGILANT_HEALTH_OPTION_METRICS = 'vigilant_healthchecks_enabled_metrics';
const VIGILANT_HEALTH_OPTION_TOKEN = 'vigilant_healthchecks_api_token';
const VIGILANT_HEALTH_SETTINGS_GROUP = 'vigilant_healthchecks_settings';
const VIGILANT_HEALTH_SETTINGS_SLUG = 'vigilant-healthchecks';

SettingsPage::boot();
HealthEndpoint::boot();
Scheduler::boot(__FILE__);
