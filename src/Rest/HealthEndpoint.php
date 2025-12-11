<?php

declare(strict_types=1);

namespace Vigilant\WordpressHealthchecks\Rest;

use Vigilant\HealthChecksBase\BuildResponse;
use Vigilant\WordpressHealthchecks\HealthCheckResponder;
use Vigilant\WordpressHealthchecks\Settings\Options;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function add_action;
use function do_action;
use function hash;
use function hash_equals;
use function preg_match;
use function register_rest_route;
use function trim;

class HealthEndpoint
{
    public static function boot(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(
            VIGILANT_HEALTH_REST_NAMESPACE,
            VIGILANT_HEALTH_REST_ROUTE,
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [self::class, 'handleRequest'],
                'permission_callback' => [self::class, 'checkPermissions'],
            ]
        );
    }

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     * @return bool|WP_Error
     */
    public static function checkPermissions(WP_REST_Request $request)
    {
        $tokenHash = Options::getApiTokenDigest();

        if ($tokenHash === '') {
            return new WP_Error(
                'vigilant_healthchecks_token_not_configured',
                '',
                ['status' => 401]
            );
        }

        $authorization = (string) $request->get_header('authorization');

        if ($authorization === '') {
            return new WP_Error(
                'vigilant_healthchecks_missing_authorization',
                __('Missing Authorization header.', 'vigilant-healthchecks'),
                ['status' => 401]
            );
        }

        $matches = [];

        if (! preg_match('/^Bearer\s+(.*)$/i', $authorization, $matches)) {
            return new WP_Error(
                'vigilant_healthchecks_invalid_authorization_scheme',
                __('Authorization header must use the Bearer scheme.', 'vigilant-healthchecks'),
                ['status' => 401]
            );
        }

        $providedToken = trim($matches[1]);

        if ($providedToken === '') {
            return new WP_Error(
                'vigilant_healthchecks_invalid_token',
                __('Invalid API token.', 'vigilant-healthchecks'),
                ['status' => 401]
            );
        }

        $providedHash = hash('sha256', $providedToken);

        if (! hash_equals($tokenHash, $providedHash)) {
            return new WP_Error(
                'vigilant_healthchecks_invalid_token',
                __('Invalid API token.', 'vigilant-healthchecks'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * @param WP_REST_Request<array<string, mixed>> $request
     */
    public static function handleRequest(WP_REST_Request $request): WP_REST_Response
    {
        $registry = Options::registry();

        do_action('vigilant_healthchecks_prepare', $registry);

        $payload = (new HealthCheckResponder(new BuildResponse(), $registry))->payload();

        return new WP_REST_Response($payload, 200);
    }
}
