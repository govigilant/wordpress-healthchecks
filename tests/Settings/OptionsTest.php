<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Tests\Settings;

use PHPUnit\Framework\TestCase;
use Vigilant\WordpressHealthchecks\Settings\Options;
use function hash;

final class OptionsTest extends TestCase
{
    private const OPTIONS_STORE = 'vigilant_healthchecks_wp_options';

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS[self::OPTIONS_STORE] = [];
    }

    public function testAvailableChecksExposeClassReferences(): void
    {
        $checks = Options::availableChecks();

        $this->assertArrayHasKey('database', $checks);
        $this->assertSame(\Vigilant\WordpressHealthchecks\Checks\DatabaseCheck::class, $checks['database']['class']);
        $this->assertSame('Database connection', $checks['database']['label']);
    }

    public function testAvailableMetricsExposeClassReferences(): void
    {
        $metrics = Options::availableMetrics();

        $this->assertArrayHasKey('memory_usage', $metrics);
        $this->assertSame(\Vigilant\HealthChecksBase\Checks\Metrics\MemoryUsageMetric::class, $metrics['memory_usage']['class']);
        $this->assertSame('Memory usage', $metrics['memory_usage']['label']);
    }

    public function testDefaultCheckTogglesEnableAllChecks(): void
    {
        $toggles = Options::defaultCheckToggles();
        $expected = array_fill_keys(array_keys(Options::availableChecks()), true);

        $this->assertSame($expected, $toggles);
    }

    public function testEnabledChecksRespectStoredOptions(): void
    {
        $this->setOption(VIGILANT_HEALTH_OPTION_CHECKS, [
            'database' => false,
            'cron' => true,
        ]);

        $toggles = Options::enabledChecks();

        $this->assertFalse($toggles['database']);
        $this->assertTrue($toggles['cron']);
    }

    public function testSanitizeToggleOptionsCastsValuesToBooleans(): void
    {
        $sanitized = Options::sanitizeCheckToggles([
            'database' => '1',
            'cron' => 0,
        ]);

        $this->assertSame([
            'database' => true,
            'site_health' => false,
            'core_version' => false,
            'redis' => false,
            'plugin_updates' => false,
            'cron' => false,
        ], $sanitized);
    }

    public function testApiTokenHelpersHashValuesAndRespectPlaceholder(): void
    {
        $hashed = Options::sanitizeApiToken("  secret  ");
        $this->assertSame(hash('sha256', 'secret'), $hashed);
        $this->setOption(VIGILANT_HEALTH_OPTION_TOKEN, $hashed);
        $this->assertSame($hashed, Options::getApiTokenDigest());
        $this->assertTrue(Options::hasApiToken());
        $this->assertSame($hashed, Options::sanitizeApiToken(Options::tokenPlaceholderValue()));
        $this->assertSame('', Options::sanitizeApiToken(null));
    }

    public function testApiTokenDigestRejectsNonHashedValues(): void
    {
        $this->setOption(VIGILANT_HEALTH_OPTION_TOKEN, 'not-hashed');
        $this->assertSame('', Options::getApiTokenDigest());
        $this->assertFalse(Options::hasApiToken());
    }

    public function testRegistryReturnsNewInstanceEachCall(): void
    {
        $first = Options::registry();
        $second = Options::registry();

        $this->assertNotSame($first, $second);
    }

    private function setOption(string $name, mixed $value): void
    {
        $GLOBALS[self::OPTIONS_STORE][$name] = $value;
    }

}
