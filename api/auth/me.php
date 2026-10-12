<?php
/* GET: {ok:true, email, kind, lang} for the signed-in account, or 401 (and the cookie cleared). */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') fail(405, 'method');
$s = current_session();
if (!$s) { clear_cookie(); fail(401, 'signed_out'); }
json_out(200, ['ok' => true, 'email' => (string)$s['email'], 'kind' => (string)$s['kind'], 'lang' => (string)$s['lang']]);
log_api('ok');
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
purge_soon();
