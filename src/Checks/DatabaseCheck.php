<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks;

use RuntimeException;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use wpdb;
use function do_action;
use function function_exists;
use function sprintf;

class DatabaseCheck extends Check
{
    protected string $type = 'database_connection';

    public function __construct(private ?wpdb $wpdb = null)
    {
    }

    public function run(): ResultData
    {
        try {
            $wpdb = $this->resolveWpdb();
            $isHealthy = $this->canConnect($wpdb);
            $databaseName = defined('DB_NAME') ? (string) DB_NAME : null;

            $message = $isHealthy
                ? ($databaseName ? "Database '{$databaseName}' connection is healthy." : 'Database connection is healthy.')
                : 'Failed to connect to the database.';
        } catch (Throwable $e) {
            $isHealthy = false;
            $message = 'Failed to connect to the database.';
            $this->reportException($e);
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
        try {
            $wpdb = $this->resolveWpdb();

            return defined('DB_USER') && DB_USER !== '';
        } catch (Throwable) {
            return false;
        }
    }

    private function canConnect(wpdb $wpdb): bool
    {
        if (! $wpdb->check_connection(false)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Intentional direct query to verify connectivity.
        $result = $wpdb->get_var('SELECT 1');

        return $result !== null;
    }

    private function reportException(Throwable $exception): void
    {
        if (! function_exists('do_action')) {
            return;
        }

        do_action('vigilant_healthchecks_check_exception', $this->type(), $exception);
    }

    private function resolveWpdb(): wpdb
    {
        if ($this->wpdb instanceof wpdb) {
            return $this->wpdb;
        }

        global $wpdb;

        if (! $wpdb instanceof wpdb) {
            throw new RuntimeException('WordPress database object is unavailable.');
        }

        return $wpdb;
    }
}
