<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Checks;

use RuntimeException;
use Throwable;
use Vigilant\HealthChecksBase\Checks\Check;
use Vigilant\HealthChecksBase\Data\ResultData;
use Vigilant\HealthChecksBase\Enums\Status;
use wpdb;
use function error_log;
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
            error_log(sprintf('[Vigilant Healthchecks] Database check failed: %s', $e->getMessage()));
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

        $result = $wpdb->get_var('SELECT 1');

        return $result !== null;
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
