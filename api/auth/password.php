<?php
/* POST {current, password}, signed in: changes the password, ends every other session of the account and
   cancels every link not yet used, so a pending email change dies with the password it was asked under (the
   change_old mail tells the owner to do exactly this). The current password is checked inside the same attempt
   limits as login. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';

$in = json_input(['current', 'password']);
ip_limit('account', 20, 100);
$s = require_session();
check_password($s, text_field($in, 'current'), 'current');
$password = text_field($in, 'password');
$problem = password_problem($password, (string)$s['email']);
if ($problem !== null) fail(400, $problem, 'password');
$hash = hash_password($password);
atomic(function () use ($s, $hash) {
    q('UPDATE accounts SET password_hash = ? WHERE id = ?', [$hash, $s['account_id']]);
    q('DELETE FROM sessions WHERE account_id = ? AND id_hash <> ?', [$s['account_id'], $s['id_hash']]);
    q('DELETE FROM tokens WHERE account_id = ? AND used_at IS NULL', [$s['account_id']]);
});
json_out(200, ['ok' => true]);
log_api('changed', (int)$s['account_id']);
