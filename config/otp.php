<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Show the one-time code in the API response (development only)
    |--------------------------------------------------------------------------
    |
    | OFF unless OTP_EXPOSE_DEV_CODE=true. Even then it only takes effect when the application environment is local,
    | development or testing - see App\Models\MobileOtp::exposesDevCode(). In production and staging the code reaches
    | the person by SMS only, whatever this says. It exists so a developer without SMS credentials can sign in on a
    | local server; it must never be set on a server that real people use.
    |
    */
    'expose_dev_code' => (bool) env('OTP_EXPOSE_DEV_CODE', false),

    // Minutes a code stays valid. Also sent to Fast2SMS as otp_expiry (see MobileOtp::TTL_MINUTES).
    'ttl_minutes' => 5,

    // Wrong guesses allowed against one code before it is dead.
    'max_attempts' => 5,

    // Seconds before the same number may be sent another code (the app's Resend button waits the same 30).
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 30),

    // Codes sent to one number in 10 minutes, and from one IP address in an hour (stops a script walking number lists).
    'send_per_phone' => 3,
    'send_per_ip_hour' => 20,
];
