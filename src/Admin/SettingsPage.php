<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Admin;

use Vigilant\WordpressHealthchecks\Settings\Options;
use function __;
use function add_action;
use function add_options_page;
use function checked;
use function current_user_can;
use function esc_attr;
use function esc_html;
use function esc_html_e;
use function register_setting;
use function settings_fields;
use function submit_button;

class SettingsPage
{
    public static function boot(): void
    {
        add_action('admin_init', [self::class, 'registerSettings']);
        add_action('admin_menu', [self::class, 'registerAdminPage']);
    }

    public static function registerSettings(): void
    {
        register_setting(
            VIGILANT_HEALTH_SETTINGS_GROUP,
            VIGILANT_HEALTH_OPTION_CHECKS,
            [
                'type' => 'array',
                'sanitize_callback' => [Options::class, 'sanitizeCheckToggles'],
                'default' => Options::defaultCheckToggles(),
            ]
        );

        register_setting(
            VIGILANT_HEALTH_SETTINGS_GROUP,
            VIGILANT_HEALTH_OPTION_METRICS,
            [
                'type' => 'array',
                'sanitize_callback' => [Options::class, 'sanitizeMetricToggles'],
                'default' => Options::defaultMetricToggles(),
            ]
        );

        register_setting(
            VIGILANT_HEALTH_SETTINGS_GROUP,
            VIGILANT_HEALTH_OPTION_TOKEN,
            [
                'type' => 'string',
                'sanitize_callback' => [Options::class, 'sanitizeApiToken'],
                'default' => '',
            ]
        );
    }

    public static function registerAdminPage(): void
    {
        add_options_page(
            __('Vigilant Healthchecks', 'vigilant-healthchecks'),
            __('Vigilant Healthchecks', 'vigilant-healthchecks'),
            'manage_options',
            VIGILANT_HEALTH_SETTINGS_SLUG,
            [self::class, 'renderSettingsPage']
        );
    }

    public static function renderSettingsPage(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $checks = Options::availableChecks();
        $metrics = Options::availableMetrics();
        $enabledChecks = Options::enabledChecks();
        $enabledMetrics = Options::enabledMetrics();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Vigilant Healthchecks', 'vigilant-healthchecks'); ?></h1>
            <p class="description">
                <a href="https://govigilant.io" target="_blank" rel="noopener noreferrer">
                    <?php esc_html_e('Integrate healthchecks with Vigilant', 'vigilant-healthchecks'); ?>
                </a>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields(VIGILANT_HEALTH_SETTINGS_GROUP); ?>
                <h2><?php esc_html_e('Authentication', 'vigilant-healthchecks'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                    <tr>
                        <th scope="row"><?php esc_html_e('API token', 'vigilant-healthchecks'); ?></th>
                        <td>
                            <input
                                type="password"
                                name="<?php echo esc_attr(VIGILANT_HEALTH_OPTION_TOKEN); ?>"
                                value="<?php echo esc_attr(Options::hasApiToken() ? Options::tokenPlaceholderValue() : ''); ?>"
                                class="regular-text"
                                autocomplete="new-password"
                                placeholder="<?php esc_attr_e('Paste a new token', 'vigilant-healthchecks'); ?>"
                            >
                            <p class="description"><?php esc_html_e('Token values are stored hashed; enter a new token to rotate it or leave the field empty to remove access.', 'vigilant-healthchecks'); ?></p>
                            <p class="description"><?php esc_html_e('Requests must send this token via the Authorization header using the Bearer scheme.', 'vigilant-healthchecks'); ?></p>
                        </td>
                    </tr>
                    </tbody>
                </table>
                <h2><?php esc_html_e('Checks', 'vigilant-healthchecks'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                    <?php foreach ($checks as $key => $check): ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($check['label']); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(VIGILANT_HEALTH_OPTION_CHECKS.'['.$key.']'); ?>" value="1" <?php checked($enabledChecks[$key] ?? true); ?>>
                                    <?php esc_html_e('Enabled', 'vigilant-healthchecks'); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <h2><?php esc_html_e('Metrics', 'vigilant-healthchecks'); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                    <?php foreach ($metrics as $key => $metric): ?>
                        <tr>
                            <th scope="row"><?php echo esc_html($metric['label']); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="<?php echo esc_attr(VIGILANT_HEALTH_OPTION_METRICS.'['.$key.']'); ?>" value="1" <?php checked($enabledMetrics[$key] ?? true); ?>>
                                    <?php esc_html_e('Enabled', 'vigilant-healthchecks'); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
