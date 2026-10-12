<?php
/* POST {t, password}: sets the new password, spends the link, ends every session of the account, cancels its
   other links not yet used (a pending email change included) and clears its address's failed attempts, in one
   transaction. Using the link proves the mailbox, so an account not yet confirmed is confirmed by it: whoever
   holds the address can always take it back from a sign-up made in their name. 410 link for a link unknown,
   used or expired. It never signs anyone in. */
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
    q('UPDATE accounts SET password_hash = ?, email_verified_at = COALESCE(email_verified_at, ?) WHERE id = ?', [$hash, now(), $id]);
    q('DELETE FROM sessions WHERE account_id = ?', [$id]);
    q('DELETE FROM tokens WHERE account_id = ? AND used_at IS NULL', [$id]);
    clear_failures(fail_key((string)q('SELECT email FROM accounts WHERE id = ?', [$id])->fetchColumn()));
    return $id;
});
if ($id === null) fail(410, 'link');
clear_cookie();
json_out(200, ['ok' => true]);
log_api('reset', $id);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
purge_soon();
