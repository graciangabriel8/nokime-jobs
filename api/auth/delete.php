<?php
/* POST {password}, signed in: the account, its sessions, its links and its failed attempts go at once; the
   cookie is cleared; a goodbye note goes to the address once the answer has left. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';
require dirname(__DIR__) . '/_auth-mail.php';

$in = json_input(['password']);
ip_limit('account', 20, 100);
$s = require_session();
check_password($s, text_field($in, 'password'), 'password');
$id = (int)$s['account_id'];
delete_account($id);
clear_cookie();
answer_now(200, ['ok' => true]);
log_api('deleted ' . send_mail('goodbye', (string)$s['email'], (string)$s['lang'], site_origin() . '/#offres'), $id);
