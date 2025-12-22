=== Vigilant Healthchecks ===
Contributors: vincentbean
Tags: healthcheck, monitoring, rest api, cron, metrics
Requires at least: 6.3
Tested up to: 6.9
Requires PHP: 8.2
Stable tag: 1.0.4
License: MIT
License URI: https://opensource.org/licenses/MIT

A WordPress plugin that provides healthchecks to your WordPress site that integrate seamlessly with Vigilant (https://govigilant.io).

== Description ==
Vigilant Healthchecks provides a REST healthcheck endpoint at `/wp-json/vigilant/v1/health` with an admin interface to conrol which checks are executed.
This plugin is designed to integrate with [Vigilant](https://govigilant.io), an all-in-one web monitoring tool which can be self-hosted.

= Key Features =
* Protected REST endpoint with configurable token.
* Built-in checks for database connectivity, core versions, plugin updates, cron freshness, Redis, and WordPress Site Health issues.
* Metrics catalogue for memory, disk, CPU load, and database size with sensible caching defaults.
* Extensible registry and hooks (`vigilant_healthchecks_prepare`, `vigilant_healthchecks_cron_threshold`, `vigilant_healthchecks_database_size_cache_ttl`) so you can add custom checks and metrics.
* Scheduler heartbeat (`vigilant_healthchecks_cron_monitor`) that validates WP-Cron is actually running and reports stale schedules.

= How It Works =
1. Configure the bearer token under **Settings → Vigilant Healthchecks**; every REST request must include `Authorization: Bearer <token>`.
2. Toggle the checks and metrics you want to expose; disabled items are never instantiated so they have zero runtime impact.
3. Call the endpoint yourself or configure your website in Vigilant.

== Installation ==
1. Upload the `wordpress-healthchecks` directory to `/wp-content/plugins/` or install via Composer (`composer require govigilant/wordpress-healthchecks`).
2. Activate **Vigilant Healthchecks** through the **Plugins** menu in WordPress.
3. Visit **Settings → Vigilant Healthchecks** to paste your Vigilant (or custom) token and select the desired checks/metrics.

== Changelog ==
= 1.0.0 =
* Initial release
