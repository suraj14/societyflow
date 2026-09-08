<?php

/*
 * Cross-Origin Resource Sharing.
 *
 * allowed_origins was hardcoded to ['*']. That is not a CSRF hole here -
 * the API authenticates with bearer tokens and supports_credentials is
 * false, so a browser will not attach cookies to a cross-origin call - but
 * it does let any website read API responses on behalf of a visitor who
 * supplies a token, and it removes a defence for no benefit once the real
 * origins are known.
 *
 * The native mobile app is unaffected either way: CORS is a browser
 * control and Dio does not enforce it.
 *
 * Set CORS_ALLOWED_ORIGINS to a comma-separated list in production, e.g.
 *   CORS_ALLOWED_ORIGINS=https://societyflow.joboapps.com
 * The default stays '*' so existing local and LAN development keeps
 * working unchanged.
 */

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*'))
)));

return [
    'paths'                    => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods'          => ['*'],
    'allowed_origins'          => $origins ?: ['*'],
    'allowed_origins_patterns' => [],
    'allowed_headers'          => ['*'],
    'exposed_headers'          => [],
    'max_age'                  => 0,
    'supports_credentials'     => false,
];
