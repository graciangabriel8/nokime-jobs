<?php
/* POST {t}: spends a confirmation link (purpose confirm: the address is confirmed) or an email-change link
   (purpose email_change: the account takes the new address, and every other link not yet used is cancelled,
   so a reset link sent to the old mailbox no longer works). 200 {ok:true}, or 410 link for a link unknown,
   used, expired, or whose new address was taken meanwhile. It never signs anyone in.
   The mails link to the page /compte/confirmer/, whose one button sends this POST: mail scanners open every
   link they see, and a link that did the work on opening was spent before its owner clicked it. A GET here
   changes nothing and answers 303 to that page, so a link mailed before the page existed still works. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') to_page();
$t = text_field(json_input(['t']), 't');
ip_limit('confirm', 30, 150);
try {
    $row = atomic(function () use ($t) {
        $tok = spend_token($t, ['confirm', 'email_change']);
        if (!$tok) return null;
        $id = (int)$tok['account_id'];
        if ($tok['purpose'] === 'confirm') {
            q('UPDATE accounts SET email_verified_at = ? WHERE id = ? AND email_verified_at IS NULL', [now(), $id]);
        } else {
            q('UPDATE accounts SET email = ?, email_verified_at = ? WHERE id = ?', [$tok['new_email'], now(), $id]);
            q('DELETE FROM tokens WHERE account_id = ? AND used_at IS NULL', [$id]);
        }
        return $tok;
    });
} catch (PDOException $e) {
    if ($e->getCode() !== '23000') throw $e;   // the new address became another account's meanwhile
    fail(410, 'link');
}
if (!$row) fail(410, 'link');
json_out(200, ['ok' => true]);
log_api($row['purpose'] === 'confirm' ? 'confirmed' : 'email_changed', (int)$row['account_id']);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
purge_soon();

/* A GET (a mail scanner, or a link mailed before the page): 303 to the page, carrying a well-formed token and
   lang=en, and nothing else. */
function to_page(): void {
    $t = $_GET['t'] ?? '';
    $q = is_string($t) && preg_match('/^[A-Za-z0-9_-]{43}$/', $t) ? '?t=' . $t : '';
    if (($_GET['lang'] ?? '') === 'en') $q .= ($q === '' ? '?' : '&') . 'lang=en';
    http_response_code(303);
    api_headers();
    header('Location: /compte/confirmer/' . $q);
    log_api('get_to_page');
    exit;
}
