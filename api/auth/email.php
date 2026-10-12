<?php
/* POST {password, new_email}, signed in: 202 {ok:true}; then a confirmation link valid 24 hours goes to the new
   address and a notice to the old one. Nothing changes until the link is used (/compte/confirmer/, whose
   button POSTs to confirm.php, purpose email_change). A new address that already has an account gets no link,
   and the answer is the same. Over the new address's mail limit no link is made, and the pending one stays. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';
require dirname(__DIR__) . '/_auth-mail.php';

$in = json_input(['password', 'new_email']);
ip_limit('account', 20, 100);
$s = require_session();
check_password($s, text_field($in, 'password'), 'password');
$new = normal_address(text_field($in, 'new_email'));
if ($new === null) fail(400, 'email_invalid', 'new_email');
if ($new === $s['email']) fail(400, 'email_same', 'new_email');

answer_now(202, ['ok' => true]);
register_shutdown_function('purge_soon');
$id = (int)$s['account_id'];
$old = (string)$s['email']; $lang = (string)$s['lang'];
if (q('SELECT 1 FROM accounts WHERE email = ?', [$new])->fetch()) {
    $sent = 'taken';
} elseif (!mail_allowed($new, true)) {
    $sent = 'mail_limit';
} else {
    $token = atomic(function () use ($id, $new) { return new_token($id, 'email_change', CONFIRM_LIFE, $new, true); });
    $sent = deliver_mail('change_new', $new, $lang, site_origin() . '/compte/confirmer/?t=' . $token);
}
log_api('requested new:' . $sent . ' old:' . send_mail('change_old', $old, $lang, site_origin() . '/compte/mot-de-passe/'), $id);
