<?php
/* POST {t, password}: sets the new password, spends the link, ends every session of the account and clears
   its failed attempts, in one transaction. 410 link for a link unknown, used or expired. It never signs anyone
   in. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';

$in = json_input(['t', 'password']);
ip_limit('reset', 20, 100);
$t = text_field($in, 't');
$password = text_field($in, 'password');

/* The rule needs the account's address, so the link is read first, then spent with the change. */
$a = preg_match('/^[A-Za-z0-9_-]{43}$/', $t) ? q('SELECT a.id, a.email FROM tokens t JOIN accounts a ON a.id = t.account_id
      WHERE t.hash = ? AND t.purpose = ? AND t.used_at IS NULL AND t.expires_at > ?', [hash('sha256', $t), 'reset', now()])->fetch() : null;
if (!$a) fail(410, 'link');
$problem = password_problem($password, (string)$a['email']);
if ($problem !== null) fail(400, $problem, 'password');
$hash = hash_password($password);
$id = atomic(function () use ($t, $hash) {
    $tok = spend_token($t, ['reset']);
    if (!$tok) return null;
    $id = (int)$tok['account_id'];
    q('UPDATE accounts SET password_hash = ? WHERE id = ?', [$hash, $id]);
    q('DELETE FROM sessions WHERE account_id = ?', [$id]);
    q('DELETE FROM login_failures WHERE account_id = ?', [$id]);
    return $id;
});
if ($id === null) fail(410, 'link');
clear_cookie();
json_out(200, ['ok' => true]);
log_api('reset', $id);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
purge_soon();
