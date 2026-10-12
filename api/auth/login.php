<?php
/* POST {email, password}: a new session and its cookie, {ok:true, kind, email}. A wrong address and a wrong
   password get the same 401 bad_credentials after the same work (a dummy hash for an unknown address) and the
   same attempt limits. An unconfirmed account gets 403 confirm_first, only after the right password, and a
   fresh confirmation link at most once an hour. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';
require dirname(__DIR__) . '/_auth-mail.php';

$in = json_input(['email', 'password']);
ip_limit('login', 20, 100);

$addr = normal_address(text_field($in, 'email'));
if ($addr === null) fail(400, 'email_invalid', 'email');
$password = text_field($in, 'password');
if ($password === '') fail(400, 'password_missing', 'password');

$a = q('SELECT id, email, kind, lang, password_hash, email_verified_at FROM accounts WHERE email = ?', [$addr])->fetch();
gate_or_fail($a ? (int)$a['id'] : null, $addr);
if (!$a) {
    if (argon_ok()) password_hash($password, PASSWORD_ARGON2ID);   // the same work as a real check
    record_failure(null, $addr);
    fail(401, 'bad_credentials');
}
$id = (int)$a['id'];
if (!password_verify($password, (string)$a['password_hash'])) {
    record_failure($id, $addr);
    fail(401, 'bad_credentials');
}
clear_failures($id);
if ($a['email_verified_at'] === null) {
    answer_now(403, ['ok' => false, 'error' => 'confirm_first']);
    $recent = q('SELECT COUNT(*) FROM tokens WHERE account_id = ? AND purpose = ? AND created_at > ?', [$id, 'confirm', now() - 3600])->fetchColumn();
    if ((int)$recent > 0) { log_api('confirm_first recent_link', $id); exit; }
    $token = new_token($id, 'confirm', CONFIRM_LIFE);
    log_api('confirm_first ' . send_mail('confirm', $addr, (string)$a['lang'], site_origin() . '/api/auth/confirm.php?t=' . $token), $id);
    exit;
}
if (argon_ok() && password_needs_rehash((string)$a['password_hash'], PASSWORD_ARGON2ID)) {
    q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_ARGON2ID), $id]);
}
$sid = new_session($id);
set_cookie($sid);
json_out(200, ['ok' => true, 'kind' => (string)$a['kind'], 'email' => (string)$a['email']]);
log_api('signed_in', $id);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
purge_soon();
