<?php

return [

    /*
    |--------------------------------------------------------------------------
    | External messaging kill switch
    |--------------------------------------------------------------------------
    |
    | SocietyFlow stores SMTP, SMS and WhatsApp credentials per society in the
    | database, and the mail layer applies them at send time - so MAIL_MAILER=log
    | alone does NOT keep a copy of a real database from emailing real servers.
    |
    | SAFE BY DEFAULT: with no EXTERNAL_MESSAGING_ENABLED in the environment nothing
    | is sent to a real provider. Set it to true only on a server that is meant to
    | deliver real messages (see docs/LOCAL_MESSAGING_TESTING.md). While it is false,
    | for any seeding, QA, browser or demo run against a database that holds
    | provider credentials:
    |   - mail is forced to the "log" driver, whatever the society's settings say;
    |   - SMS, Fast2SMS and WhatsApp HTTP calls are not made (a "suppressed"
    |     result is returned and logged instead).
    | Tests that install a transport override are unaffected: they never touch
    | the network.
    |
    */
    'enabled' => (bool) env('EXTERNAL_MESSAGING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Test-recipient allow-list (local / staging safety)
    |--------------------------------------------------------------------------
    |
    | EXTERNAL_MESSAGING_ALLOWLIST="me@example.com,9876543210"
    |
    | When this variable is present in the environment, real delivery goes ONLY to the e-mail addresses and 10-digit mobile numbers
    | listed (comma separated); every other recipient is simulated, so a copy of the real database cannot message real residents.
    | Present but empty means "nobody". Absent (the default) means no restriction - which is what production uses once it is
    | approved to send. Only matters while EXTERNAL_MESSAGING_ENABLED is true.
    |
    */
    // kept RAW: App\Support\ExternalMessaging parses it, fails closed on any malformed entry and matches exactly after normalising.
    'allowlist' => env('EXTERNAL_MESSAGING_ALLOWLIST') === null ? null : (string) env('EXTERNAL_MESSAGING_ALLOWLIST'),

    /*
    |--------------------------------------------------------------------------
    | Duplicate suppression window (seconds)
    |--------------------------------------------------------------------------
    |
    | The same notification (same channel, society, recipient number, type and
    | content) that was already sent successfully is not sent again inside this
    | window - see App\Support\SendOnce. Covers an event that fires twice and a
    | queue retry after a send that did go out.
    |
    */
    'duplicate_window' => (int) env('MESSAGING_DUPLICATE_WINDOW', 300),

];
