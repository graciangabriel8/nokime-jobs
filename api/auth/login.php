<?php
/* POST {email, password}: a new session and its cookie, {ok:true, kind, email}. A wrong address, a wrong
   password and an account not yet confirmed all get the same 401 bad_credentials after the same work (a dummy
   hash for an unknown address), each counted by the same attempt limits. The unconfirmed account cannot answer
   otherwise: its password is whatever its sign-up chose, so a distinct answer would let anyone tell a new
   address from an existing account by signing up and then signing in. With the right password it gets a fresh
   confirmation link, at most one an hour. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';
require dirname(__DIR__) . '/_auth-mail.php';

$in = json_input(['email', 'password']);
ip_limit('login', 20, 100);

$addr = normal_address(text_field($in, 'email'));
if ($addr === null) fail(400, 'email_invalid', 'email');
$password = text_field($in, 'password');
if ($password === '') fail(400, 'password_missing', 'password');

$k = fail_key($addr);
gate_or_fail($k);   // this attempt is counted before the password is checked
$a = q('SELECT id, email, kind, lang, password_hash, email_verified_at FROM accounts WHERE email = ?', [$addr])->fetch();
if (!$a) {
    if (argon_ok()) password_hash($password, PASSWORD_ARGON2ID);   // the same work as a real check
    fail(401, 'bad_credentials');
}
$id = (int)$a['id'];
if (!password_verify($password, (string)$a['password_hash'])) fail(401, 'bad_credentials');
if ($a['email_verified_at'] === null) {
    answer_now(401, ['ok' => false, 'error' => 'bad_credentials']);
    $recent = q('SELECT COUNT(*) FROM tokens WHERE account_id = ? AND purpose = ? AND created_at > ?', [$id, 'confirm', now() - 3600])->fetchColumn();
    if ((int)$recent > 0) { log_api('unconfirmed recent_link', $id); exit; }
    $token = new_token($id, 'confirm', CONFIRM_LIFE);
    log_api('unconfirmed ' . send_mail('confirm', $addr, (string)$a['lang'], site_origin() . '/compte/confirmer/?t=' . $token), $id);
    exit;
}
clear_failures($k);
if (argon_ok() && password_needs_rehash((string)$a['password_hash'], PASSWORD_ARGON2ID)) {
    q('UPDATE accounts SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_ARGON2ID), $id]);
}
$sid = new_session($id);
set_cookie($sid);
json_out(200, ['ok' => true, 'kind' => (string)$a['kind'], 'email' => (string)$a['email']]);
log_api('signed_in', $id);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
purge_soon();
