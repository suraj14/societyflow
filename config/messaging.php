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
    | Set EXTERNAL_MESSAGING_ENABLED=false for any seeding, QA, browser or demo
    | run against a database that holds provider credentials. Then:
    |   - mail is forced to the "log" driver, whatever the society's settings say;
    |   - SMS, Fast2SMS and WhatsApp HTTP calls are not made (a "suppressed"
    |     result is returned and logged instead).
    | Tests that install a transport override are unaffected: they never touch
    | the network.
    |
    */
    'enabled' => (bool) env('EXTERNAL_MESSAGING_ENABLED', true),

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
