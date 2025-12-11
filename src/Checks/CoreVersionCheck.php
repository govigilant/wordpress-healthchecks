<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks;

use RuntimeException;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use function apply_filters;
use function array_filter;
use function get_bloginfo;
use function version_compare;

class CoreVersionCheck extends Check
{
    protected string $type = 'wordpress_core_version';

    public function run(): ResultData
    {
        try {
            $this->ensureUpdateFunctionsLoaded();

            $currentVersion = $this->getCurrentVersion();
            $latestVersion = $this->fetchLatestVersion();

            if ($latestVersion === null) {
                return $this->buildResult(
                    Status::Warning,
                    'Unable to determine the latest WordPress version.',
                    $currentVersion,
                    null
                );
            }

            $isLatest = version_compare($currentVersion, $latestVersion, '>=');

            $status = $isLatest ? Status::Healthy : Status::Warning;
            $message = $isLatest
                ? sprintf('WordPress core is up to date (version %s).', $currentVersion)
                : sprintf('WordPress core update available: %s → %s.', $currentVersion, $latestVersion);

            return $this->buildResult($status, $message, $currentVersion, $latestVersion);
        } catch (RuntimeException $exception) {
            return $this->buildResult(Status::Warning, $exception->getMessage(), $this->getCurrentVersion(), null);
        }
    }

    public function available(): bool
    {
        try {
            $this->ensureUpdateFunctionsLoaded();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    private function ensureUpdateFunctionsLoaded(): void
    {
        if (! function_exists('wp_version_check')) {
            require_once ABSPATH . 'wp-includes/update.php';
        }

        if (! function_exists('get_core_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
        }

        if (! function_exists('wp_version_check')) {
            throw new RuntimeException('WordPress update functions are unavailable.');
        }
    }

    private function fetchLatestVersion(): ?string
    {
        $forced = apply_filters('vigilant_healthchecks_force_core_update_check', false);
        wp_version_check([], (bool) $forced);

        $updates = get_core_updates();

        if (empty($updates)) {
            return null;
        }

        foreach ($updates as $update) {
            if (($update->response ?? null) === 'upgrade' && ! empty($update->current)) {
                return $update->current;
            }

            if (($update->response ?? null) === 'latest' && ! empty($update->current)) {
                return $update->current;
            }
        }

        return null;
    }

    private function getCurrentVersion(): string
    {
        return (string) get_bloginfo('version');
    }

    private function buildResult(Status $status, string $message, ?string $currentVersion, ?string $latestVersion): ResultData
    {
        $data = array_filter([
            'current_version' => $currentVersion,
            'latest_version' => $latestVersion,
        ], static fn ($value) => $value !== null);

        return ResultData::make([
            'type' => $this->type(),
            'key' => null,
            'status' => $status,
            'message' => $message,
            'data' => $data ?: null,
        ]);
    }
}
