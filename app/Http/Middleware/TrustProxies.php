<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Read from config('app.trusted_proxies') (env TRUSTED_PROXIES) so a
     * deployment behind nginx / a load balancer / Cloudflare can declare
     * its proxy and have HTTPS, the client IP and secure cookies detected
     * correctly. Unset = trust nobody, which is right for direct exposure.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    public function __construct()
    {
        $configured = trim((string) config('app.trusted_proxies', ''));

        if ($configured === '') {
            $this->proxies = null;
        } elseif ($configured === '*' || $configured === '**') {
            $this->proxies = $configured;
        } else {
            $this->proxies = array_values(array_filter(array_map('trim', explode(',', $configured))));
        }
    }

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;
}
