<?php
/* GET ?t=: spends a confirmation link (purpose confirm: the address is confirmed) or an email-change link
   (purpose email_change: the account takes the new address, and every other link not yet used is cancelled,
   so a reset link sent to the old mailbox no longer works). 303 to /compte/?ok=confirm, or to /compte/?err=link
   for a link unknown, used, expired, or whose new address was taken meanwhile, with &lang=en when the link
   carried it. It never signs anyone in. */
declare(strict_types=1);
require dirname(__DIR__) . '/_auth.php';

function back(string $q, string $outcome, ?int $account = null): void {
    http_response_code(303);
    api_headers();
    header('Location: /compte/?' . $q . (($_GET['lang'] ?? '') === 'en' ? '&lang=en' : ''));
    log_api($outcome, $account);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') fail(405, 'method');
if (cfg('per_ip_limits') && take(rk('confirm:' . ip_key()), [900 => lim(30), 86400 => lim(150)]) === null) back('err=link', 'too_many');
$t = $_GET['t'] ?? '';
if (!is_string($t)) back('err=link', 'gone');
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
    back('err=link', 'taken');
}
if (!$row) back('err=link', 'gone');
register_shutdown_function('purge_soon');
back('ok=confirm', $row['purpose'] === 'confirm' ? 'confirmed' : 'email_changed', (int)$row['account_id']);
