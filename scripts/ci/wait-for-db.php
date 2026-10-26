<?php

/**
 * Waits until the MySQL / MariaDB server named by DB_HOST, DB_PORT, DB_USERNAME and DB_PASSWORD accepts a connection.
 *
 * Bitbucket Pipelines starts a service container (definitions.services in bitbucket-pipelines.yml) next to the build but has no readiness
 * check: the first test would otherwise race the database's start-up. Exit 0 = ready, 1 = still not ready after DB_WAIT_SECONDS (default 120).
 * Failure details are deliberately not printed (an exception message can name the host and user).
 */
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$user = getenv('DB_USERNAME') ?: 'root';
$pass = getenv('DB_PASSWORD') ?: '';
$limit = (int) (getenv('DB_WAIT_SECONDS') ?: 120);

$deadline = time() + $limit;
do {
    try {
        new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
        fwrite(STDOUT, "database is ready\n");
        exit(0);
    } catch (Throwable $e) {
        fwrite(STDERR, '.');
        sleep(2);
    }
} while (time() < $deadline);

fwrite(STDERR, "\nthe database did not accept a connection within {$limit} s\n");
exit(1);
