<?php
/* POST {}: this session's row goes and the cookie is cleared. The cookie goes even when the database does not
   answer, so a shared computer is signed out whatever happens; the 503 then says the row did not. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';

json_input([]);
$s = null; $done = true;
try {
    $s = current_session();
    if ($s) q('DELETE FROM sessions WHERE id_hash = ?', [$s['id_hash']]);
} catch (Throwable $e) {
    log_php('logout: ' . describe($e));
    $done = false;
}
clear_cookie();
json_out($done ? 200 : 503, $done ? ['ok' => true] : ['ok' => false, 'error' => 'unavailable']);
log_api($done ? 'signed_out' : 'unavailable', $s ? (int)$s['account_id'] : null);
