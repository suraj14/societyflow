<?php

/*
|--------------------------------------------------------------------------
| SMS defaults taken from the environment
|--------------------------------------------------------------------------
|
| Read ONLY through config() (SmsService::getSetting): calling env() outside a config file returns null once
| `php artisan config:cache` has run - the normal production step - which silently switched the .env fallback for the
| Fast2SMS key / route / sender / template / OTP id off in production while it still worked on a developer's machine.
| A society's own saved settings always win; these are the platform-wide fallback only, and they never override the
| "Integration active" switch.
*/

return [
    'provider' => env('SMS_PROVIDER'),

    'fast2sms' => [
        'api_key'     => env('FAST2SMS_API_KEY'),
        'route'       => env('FAST2SMS_ROUTE'),
        'sender_id'   => env('FAST2SMS_SENDER_ID'),
        'template_id' => env('FAST2SMS_TEMPLATE_ID'),
        'otp_id'      => env('FAST2SMS_OTP_ID'),
        'flash'       => env('FAST2SMS_FLASH'),
    ],
];