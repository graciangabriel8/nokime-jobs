<?php
/* POST {email, password, kind, lang, age15, terms}: the same 202 {ok:true}, with the same work before it,
   whether the address is new or not. Once the answer has left, a new address gets its account (unconfirmed)
   and a confirmation link valid 24 hours; an existing one gets a mail saying someone tried, with links to sign
   in and to reset. A malformed field answers 400 naming it, which says nothing about any account. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';
require dirname(__DIR__) . '/_auth-mail.php';

$in = json_input(['email', 'password', 'kind', 'lang', 'age15', 'terms']);
ip_limit('signup', 10, 50);

$addr = normal_address(text_field($in, 'email'));
if ($addr === null) fail(400, 'email_invalid', 'email');
$kind = $in['kind'] ?? '';
if (!in_array($kind, ['candidate', 'establishment'], true)) fail(400, 'kind_missing', 'kind');
$lang = $in['lang'] ?? 'fr';
if (!in_array($lang, ['fr', 'en'], true)) fail(400, 'invalid', 'lang');
if (array_key_exists('age15', $in) && !is_bool($in['age15'])) fail(400, 'invalid', 'age15');
if ($kind === 'candidate' && ($in['age15'] ?? false) !== true) fail(400, 'age_required', 'age15');
if (($in['terms'] ?? false) !== true) fail(400, 'terms_required', 'terms');
$password = text_field($in, 'password');
$problem = password_problem($password, $addr);
if ($problem !== null) fail(400, $problem, 'password');
$hash = hash_password($password);

answer_now(202, ['ok' => true]);
register_shutdown_function('purge_soon');

$a = q('SELECT id, lang FROM accounts WHERE email = ?', [$addr])->fetch();
if (!$a) {
    try {
        $made = atomic(function () use ($addr, $kind, $lang, $hash) {
            q('INSERT INTO accounts (email, kind, lang, password_hash, email_verified_at, created_at, last_seen_at, inactive_warned_at)
               VALUES (?, ?, ?, ?, NULL, ?, ?, NULL)', [$addr, $kind, $lang, $hash, now(), now()]);
            $id = (int)db()->lastInsertId();
            return [$id, new_token($id, 'confirm', CONFIRM_LIFE)];
        });
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') throw $e;   // two sign-ups of one address at once: the second is "existing"
        $made = null;
        $a = q('SELECT id, lang FROM accounts WHERE email = ?', [$addr])->fetch();
    }
    if ($made !== null) {
        [$account, $token] = $made;
        log_api('created ' . send_mail('confirm', $addr, $lang, site_origin() . '/api/auth/confirm.php?t=' . $token), $account);
        exit;
    }
}
if ($a) log_api('existing ' . send_mail('exists', $addr, (string)$a['lang'], site_origin() . '/compte/', site_origin() . '/compte/mot-de-passe/'));
