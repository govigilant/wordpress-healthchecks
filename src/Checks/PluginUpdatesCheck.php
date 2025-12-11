<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks;

use RuntimeException;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use function apply_filters;
use function get_site_transient;
use function is_array;
use function is_string;

class PluginUpdatesCheck extends Check
{
    protected string $type = 'wordpress_plugin_updates';

    public function run(): ResultData
    {
        $this->ensureFunctionsLoaded();

        $force = apply_filters('vigilant_healthchecks_force_plugin_update_check', false);
        if ($force && function_exists('wp_update_plugins')) {
            wp_update_plugins();
        }

        $updates = get_site_transient('update_plugins');
        $outdatedPlugins = $this->collectOutdatedPlugins($updates);
        $count = count($outdatedPlugins);

        $status = $count === 0 ? Status::Healthy : Status::Warning;
        $message = $count === 0
            ? 'All plugins are up to date.'
            : sprintf('%d plugin update(s) available.', $count);

        return ResultData::make([
            'type' => $this->type(),
            'key' => null,
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function available(): bool
    {
        try {
            $this->ensureFunctionsLoaded();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function ensureFunctionsLoaded(): void
    {
        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (! function_exists('wp_update_plugins')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }

        if (! function_exists('get_plugins') || ! function_exists('wp_update_plugins')) {
            throw new RuntimeException('Plugin update functions are unavailable.');
        }
    }

    /**
     * @param mixed $updates
     * @return array<int, array{plugin: string, current_version: string|null, new_version: string|null}>
     */
    private function collectOutdatedPlugins(mixed $updates): array
    {
        $outdated = [];

        if (! is_object($updates) || empty($updates->response) || ! is_array($updates->response)) {
            return $outdated;
        }

        foreach ($updates->response as $pluginFile => $info) {
            if (! is_object($info)) {
                continue;
            }

            $pluginName = $info->slug ?? $pluginFile;
            $current = $info->Version ?? $info->version ?? null;
            $new = $info->new_version ?? null;

            $outdated[] = [
                'plugin' => is_string($pluginName) ? $pluginName : (string) $pluginFile,
                'current_version' => is_string($current) ? $current : null,
                'new_version' => is_string($new) ? $new : null,
            ];
        }

        return $outdated;
    }
}
