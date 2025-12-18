<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$GLOBALS['vigilant_healthchecks_wp_options'] = [];

(static function (): void {
    $constants = [
        'VIGILANT_HEALTH_REST_NAMESPACE' => 'vigilant/v1',
        'VIGILANT_HEALTH_REST_ROUTE' => '/health',
        'VIGILANT_HEALTH_CRON_HOOK' => 'vigilant_healthchecks_cron_monitor',
        'VIGILANT_HEALTH_LAST_CRON_OPTION' => 'vigilant_healthchecks_last_cron_run',
        'VIGILANT_HEALTH_OPTION_CHECKS' => 'vigilant_healthchecks_enabled_checks',
        'VIGILANT_HEALTH_OPTION_METRICS' => 'vigilant_healthchecks_enabled_metrics',
        'VIGILANT_HEALTH_OPTION_TOKEN' => 'vigilant_healthchecks_api_token',
        'VIGILANT_HEALTH_SETTINGS_GROUP' => 'vigilant_healthchecks_settings',
        'VIGILANT_HEALTH_SETTINGS_SLUG' => 'vigilant-healthchecks',
    ];

foreach ($constants as $name => $value) {
    if (! defined($name)) {
        define($name, $value);
    }
}
})();

/*
 * Provide minimal WordPress shims for the test suite.
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound
 */
if (! function_exists('__')) {
    function __($text)
    {
        return $text;
    }
}

if (! function_exists('get_option')) {
    function get_option(string $name, mixed $default = null): mixed
    {
        return $GLOBALS['vigilant_healthchecks_wp_options'][$name] ?? $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $name, mixed $value, $autoload = null): bool
    {
        $GLOBALS['vigilant_healthchecks_wp_options'][$name] = $value;

        return true;
    }
}

if (! function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $value, bool $remove_breaks = false): string
    {
        $clean = preg_replace('/<!--.*?-->/s', '', $value) ?? '';
        $clean = preg_replace('/<[^>]+>/', '', $clean) ?? '';

        if ($remove_breaks) {
            $clean = preg_replace('/[\r\n\t ]+/', ' ', $clean) ?? '';
        }

        return trim($clean);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $value): string
    {
        $clean = wp_strip_all_tags($value);
        $clean = preg_replace('/[\r\n\t\0\x0B]+/', ' ', $clean) ?? '';

        return trim($clean);
    }
}
/* phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound */
