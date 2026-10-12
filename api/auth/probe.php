<?php
/* GET, only while probe_mode is on (404 otherwise): what this host's PHP and database really offer. On OVH,
   for the dark test: curl -s https://jobs.nokime.fr/api/auth/probe.php */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';

if (!cfg('probe_mode')) { http_response_code(404); api_headers(); exit; }
$driver = db()->getAttribute(PDO::ATTR_DRIVER_NAME);
json_out(200, ['ok' => true, 'php' => PHP_VERSION, 'argon2id' => argon_ok(), 'algos' => password_algos(),
    'ffr' => function_exists('fastcgi_finish_request'), 'driver' => $driver,
    'sql' => $driver === 'mysql' ? q('SELECT VERSION()')->fetchColumn() . ' ' . q('SELECT @@SESSION.sql_mode')->fetchColumn() : 'sqlite']);
log_api('ok');
