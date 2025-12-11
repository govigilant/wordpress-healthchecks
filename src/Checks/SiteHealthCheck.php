<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks;

use RuntimeException;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use function apply_filters;

class SiteHealthCheck extends Check
{
    protected string $type = 'wordpress_site_health';

    public function __construct(private ?\WP_Site_Health $siteHealth = null) {}

    public function run(): ResultData
    {
        try {
            $siteHealth = $this->resolveSiteHealth();
            $criticalIssues = $this->collectCriticalIssues($siteHealth);
            $issueCount = count($criticalIssues);

            $isHealthy = $issueCount === 0;
            $message = $isHealthy
                ? 'WordPress Site Health reports no critical issues.'
                : sprintf('WordPress Site Health reports %d critical issue(s).', $issueCount);
        } catch (RuntimeException $exception) {
            $criticalIssues = [];
            $isHealthy = false;
            $message = $exception->getMessage();
        }

        $details = $isHealthy ? [] : array_map(static function (array $issue): string {
            return $issue['label'] ?? $issue['description'] ?? ($issue['test'] ?? 'Unknown test');
        }, $criticalIssues);

        return ResultData::make([
            'type' => $this->type(),
            'key' => null,
            'status' => $isHealthy ? Status::Healthy : Status::Unhealthy,
            'message' => $message,
        ]);
    }

    public function available(): bool
    {
        try {
            $this->resolveSiteHealth();

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectCriticalIssues(\WP_Site_Health $siteHealth): array
    {
        $tests = $siteHealth->get_tests();
        $issues = [];

        foreach ($tests['direct'] ?? [] as $test) {
            $result = $this->executeTest($siteHealth, $test);

            if (is_array($result) && ($result['status'] ?? null) === 'critical') {
                $issues[] = $result;
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $test
     * @return array<string, mixed>|null
     */
    private function executeTest(\WP_Site_Health $siteHealth, array $test): ?array
    {
        if (! isset($test['test'])) {
            return null;
        }

        /** @var callable(): array<string, mixed>|null $callback */
        $callback = null;

        if (is_string($test['test'])) {
            $method = sprintf('get_test_%s', $test['test']);
            if (method_exists($siteHealth, $method) && is_callable([$siteHealth, $method])) {
                $callback = [$siteHealth, $method];
            }
        }

        if (! $callback && is_callable($test['test'])) {
            $callback = $test['test'];
        }

        if (! is_callable($callback)) {
            return null;
        }

        /**
         * Replicate WP_Site_Health::perform_test() logic to keep filter compatibility.
         */
        return apply_filters('site_status_test_result', call_user_func($callback));
    }

    private function resolveSiteHealth(): \WP_Site_Health
    {
        if ($this->siteHealth instanceof \WP_Site_Health) {
            return $this->siteHealth;
        }

        $this->bootstrapAdminDependencies();

        if (! class_exists('\WP_Site_Health')) {
            $path = ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
            if (! file_exists($path)) {
                throw new RuntimeException('WP_Site_Health class is unavailable.');
            }

            require_once $path;
        }

        return $this->siteHealth = new \WP_Site_Health();
    }

    private function bootstrapAdminDependencies(): void
    {
        if (! function_exists('get_core_updates')) {
            require_once ABSPATH . 'wp-admin/includes/admin.php';
        }

        if (! function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (! function_exists('wp_get_themes')) {
            require_once ABSPATH . 'wp-includes/theme.php';
        }

        if (! class_exists('WP_Debug_Data')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
        }

        if (! function_exists('wp_check_php_version')) {
            require_once ABSPATH . 'wp-admin/includes/misc.php';
        }
    }
}
