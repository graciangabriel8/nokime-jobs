<?php
/* POST {email}: always 202 {ok:true}. Once the answer has left, an account gets a reset link valid one hour
   and single use; the new link cancels the earlier ones. An account not yet confirmed gets one too, and
   reset.php confirms it: that is how the owner of an address takes it back from a sign-up made in their name,
   or recovers from a second sign-up whose password was not kept. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';
require dirname(__DIR__) . '/_auth-mail.php';

$in = json_input(['email']);
ip_limit('forgot', 5, 20);
$addr = normal_address(text_field($in, 'email'));
if ($addr === null) fail(400, 'email_invalid', 'email');

answer_now(202, ['ok' => true]);
register_shutdown_function('purge_soon');
$a = q('SELECT id, lang FROM accounts WHERE email = ?', [$addr])->fetch();
if (!$a) { log_api('no_account'); exit; }
$token = atomic(function () use ($a) { return new_token((int)$a['id'], 'reset', RESET_LIFE, null, true); });
log_api('reset_link ' . send_mail('reset', $addr, (string)$a['lang'], site_origin() . '/compte/mot-de-passe/?t=' . $token), (int)$a['id']);
