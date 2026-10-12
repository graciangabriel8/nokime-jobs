<?php
/* Nokime Jobs accounts (phase 1): what every endpoint in api/auth/ shares. Copied from Copius's api/_lib.php
   (ingredients-dictionary, origin/main) and adapted: Jobs signs in by password, Copius by emailed link; grants,
   subscriptions and payments are Copius-only and gone. The build brief is kairos/jobs-comptes-phase1-brief.md.
   Names starting with "_" answer 404 over HTTP (.htaccess), so this file is only ever included.
   Written for PHP 7.4 and later, like the rest of api/. */
declare(strict_types=1);

const ORIGIN = 'https://jobs.nokime.fr';
const COOKIE = '__Host-jobs_session';
const SESSION_LIFE = 30 * 86400;     // a session lasts 30 days from its last use
const SEEN_EVERY = 3600;             // last_seen_at moves at most once an hour
const CONFIRM_LIFE = 86400;          // confirmation and email-change links: 24 hours
const RESET_LIFE = 3600;             // reset links: 1 hour
const MAX_BODY = 8192;               // a POST body is under 8 KB
const FREE_FAILURES = 5;             // consecutive failures before the growing delay
const LOCK_FAILURES = 25;            // failures in 24 h that lock the account until a reset
const MAX_WAIT = 3600;               // the growing delay stops at 60 min, so 25 failures fit in about 15 h (ASSUMED)
const LOCKED_FOR_GOOD = 2147483647;  // locked_until for "until a password reset"
const MAIL_SITE_CAP = 150;           // mails an hour for the whole site, under the mailbox's SMTP quota (ASSUMED)
const MAIL_RESERVE = 50;             // of those, kept for mails no sign-up can trigger: reset, link again, changes (ASSUMED)

$T0 = microtime(true);
/* jobs-private sits beside the site folder (<home>/jobs-private), as api/_offres.php finds it. */
define('PRIV', dirname(__DIR__, 2) . '/jobs-private');
date_default_timezone_set('Europe/Paris');
ini_set('display_errors', '0');   // never print a warning (it would show server paths)
header_remove('X-Powered-By');

/* Errors go to php-<host>-<date>.log through these handlers, never to the page. A warning silenced with @
   stays silent, so a refused SMTP connect is an outcome, not an exception. */
set_error_handler(function (int $no, string $msg, string $file, int $line): bool {
    if (!(error_reporting() & $no)) return false;
    throw new ErrorException($msg, 0, $no, $file, $line);
});
set_exception_handler(function (Throwable $e): void {
    log_php(describe($e));
    if (!headers_sent()) json_out(500, ['ok' => false, 'error' => 'server']);
});
register_shutdown_function(function (): void {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        log_php('fatal: ' . $e['message'] . ' at ' . basename($e['file']) . ':' . $e['line']);
    }
});

/* A database error is logged by its codes alone: a driver's message can quote a value (an address). */
function describe(Throwable $e): string {
    $what = $e instanceof PDOException
        ? 'PDOException ' . $e->getCode() . '/' . ($e->errorInfo[1] ?? '')
        : get_class($e) . ': ' . $e->getMessage();
    return $what . ' at ' . basename($e->getFile()) . ':' . $e->getLine();
}

/* config.php and hmac.key are written by Gabriel (api/config.example.php says how); PHP never makes either.
   Without them every endpoint answers 503. */
$CFG = is_file(PRIV . '/config.php') ? require PRIV . '/config.php' : null;
$KEY = @file_get_contents(PRIV . '/hmac.key');
if (!is_array($CFG) || !is_string($KEY) || strlen($KEY) !== 32) {
    log_php(!is_array($CFG) ? 'config.php is missing' : 'hmac.key is missing or not 32 bytes');
    if (!isset($_SERVER['REQUEST_METHOD'])) { fwrite(STDERR, "jobs-private/config.php or hmac.key is missing\n"); exit(1); }
    json_out(503, ['ok' => false, 'error' => 'unavailable']); exit;
}

function cfg(string $k) { global $CFG; return $CFG[$k] ?? null; }

/* The site's own address, for the Origin check and the links in mails: jobs.nokime.fr, or the rig's address
   while probe_mode is on. Never the Host header. */
function site_origin(): string {
    return rig() ? cfg('probe_origin') : ORIGIN;
}

/* The local rig: probe_mode on and another origin set. probe_mode alone (the dark test on OVH) only opens
   probe.php and marks the log lines; it never raises a limit on the real host. */
function rig(): bool {
    return cfg('probe_mode') && is_string(cfg('probe_origin')) && cfg('probe_origin') !== ORIGIN;
}

/* ---------- answers ---------- */

/* Every answer. The Expires stops OVH's mod_expires from adding a max-age of its own. */
function api_headers(): void {
    header('Cache-Control: private, no-store');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Robots-Tag: noindex');
    header('Referrer-Policy: no-referrer');
}

function json_out(int $code, array $body): void {
    http_response_code($code);
    api_headers();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/* {ok:false, error, field?}, logged, and the request ends. */
function fail(int $code, string $error, ?string $field = null, array $extra = []): void {
    json_out($code, ['ok' => false, 'error' => $error] + ($field !== null ? ['field' => $field] : []) + $extra);
    log_api($error);
    exit;
}

/* The answer leaves now; what follows (mail, purge) runs after it, so its timing says nothing. */
function answer_now(int $code, array $body): void {
    json_out($code, $body);
    ignore_user_abort(true);
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
}

/* A POST from the site's own pages and nowhere else: JSON, the site's Origin, under 8 KB, known fields only.
   A form on another site can send neither the Origin nor the content type. */
function json_input(array $fields): array {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') fail(405, 'method');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
    if (($origin !== ORIGIN && $origin !== site_origin()) || ($site !== '' && $site !== 'same-origin')) fail(403, 'origin');
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) fail(415, 'content_type');
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > MAX_BODY) fail(413, 'too_large');
    $raw = (string)file_get_contents('php://input', false, null, 0, MAX_BODY + 1);
    if (strlen($raw) > MAX_BODY) fail(413, 'too_large');
    $in = json_decode($raw, true);
    if (!is_array($in) || ($in !== [] && array_keys($in) === range(0, count($in) - 1))) fail(400, 'json');
    foreach (array_keys($in) as $k) {
        if (!in_array($k, $fields, true)) fail(400, 'unknown_field', preg_match('/^[a-z0-9_]{1,32}$/', (string)$k) ? (string)$k : null);
    }
    return $in;
}

/* One string field, or a 400 naming it. */
function text_field(array $in, string $k): string {
    $v = $in[$k] ?? '';
    if (!is_string($v)) fail(400, 'invalid', $k);
    return $v;
}

function now(): int { return time(); }

/* ---------- storage ---------- */

$PDO = null;
function db(): PDO {
    global $PDO;
    if ($PDO) return $PDO;
    $d = cfg('db');
    $PDO = new PDO($d['dsn'], $d['user'] ?? null, $d['pass'] ?? null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    return $PDO;
}

/* Let the connection go before a long wait (SMTP and its retry): the database takes 30 connections at once. */
function db_release(): void { global $PDO; $PDO = null; }

function q(string $sql, array $args = []): PDOStatement {
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

/* Several statements as one; rolled back and rethrown on any error. */
function atomic(callable $f) {
    $pdo = db();
    $pdo->beginTransaction();
    try { $r = $f(); $pdo->commit(); return $r; }
    catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}

/* ---------- addresses ---------- */

/* Trimmed and lower-cased; null for anything that is not printable ASCII or not shaped like an address. The
   email columns are ascii_bin, so a lookalike with an accent can never match another account. */
function normal_address(string $s): ?string {
    $s = strtolower(trim($s));
    if (strlen($s) > 254 || !preg_match('/^[\x21-\x7e]+@[\x21-\x7e]+$/', $s) || substr_count($s, '@') !== 1) return null;
    if (!filter_var($s, FILTER_VALIDATE_EMAIL) || strpbrk($s, '",;<>()[]\\') !== false) return null;
    return $s;
}

/* ---------- passwords (CNIL délibération 2022-100) ---------- */

/* Common patterns a password may not contain, lower-cased. Short on purpose: the length and the four classes
   do most of the work. */
const COMMON = ['azerty', 'qwerty', 'qwertz', 'password', 'motdepasse', 'mot2passe', 'nokime', 'jobs1234',
                '123456', '234567', '345678', '456789', '654321', 'abcdef', 'abc123', 'iloveyou', 'soleil123',
                'bonjour123', 'loulou123', 'doudou123', 'chouchou'];

/* Null when the password meets the rule, else what it misses. */
function password_problem(string $p, string $addr): ?string {
    if (!preg_match('//u', $p)) return 'password_invalid';
    $n = preg_match_all('/./us', $p);
    if ($n < 12) return 'password_short';
    if ($n > 128) return 'password_long';
    if (!preg_match('/\p{Lu}/u', $p) || !preg_match('/\p{Ll}/u', $p) || !preg_match('/[0-9]/', $p)
        || !preg_match('/[^\p{L}\p{N}\s]/u', $p)) return 'password_classes';
    $low = function_exists('mb_strtolower') ? mb_strtolower($p, 'UTF-8') : strtolower($p);
    $local = (string)strstr($addr, '@', true);
    if (strlen($local) >= 4 && strpos($low, $local) !== false) return 'password_email';
    foreach (COMMON as $c) if (strpos($low, $c) !== false) return 'password_common';
    if (preg_match('/(.)\1{3}/u', $low)) return 'password_common';
    return null;
}

/* Argon2id or nothing. Without it no account can be created and no password set: the request answers 503 and
   the log says why. There is no fallback to another algorithm. */
function argon_ok(): bool {
    return defined('PASSWORD_ARGON2ID') && in_array('argon2id', password_algos(), true);
}

function hash_password(string $p): string {
    if (!argon_ok()) {
        log_php('ARGON2ID MISSING: this PHP build cannot hash with Argon2id, so no account can be created and no password set');
        fail(503, 'unavailable');
    }
    return password_hash($p, PASSWORD_ARGON2ID);
}

/* ---------- attempt limits (per account) ---------- */

/* One counter per address, whether an account has it or not, so the answers never tell whether one exists:
   the row is keyed on the address's HMAC, survives the account's deletion, and is cleared only by the right
   password or a reset. Every attempt is counted before the password is checked, in one statement that also
   refuses it while the address waits, so parallel guesses cannot all slip through on one stale count.
   After five failures each further attempt waits 1, 2, 4… minutes, at most MAX_WAIT; 25 in a streak lock the
   address until a reset. A streak whose first failure is over 24 h old starts again. */
function fail_key(string $addr): string { return rk('lf:' . $addr); }

/* Counts this attempt and answers null, or refuses it: [423, 'locked', 0] or [429, 'wait', seconds]. */
function claim_attempt(string $k): ?array {
    for ($try = 0; $try < 3; $try++) {
        $claimed = atomic(function () use ($k) {
            $n = now();
            /* count is assigned before first_at: MySQL evaluates the SET list left to right with the new values,
               SQLite with the old ones, and this order gives both engines the same result. */
            if (q('UPDATE login_failures SET count = CASE WHEN first_at < ? THEN 1 ELSE count + 1 END,
                   first_at = CASE WHEN first_at < ? THEN ? ELSE first_at END, last_at = ?
                   WHERE k = ? AND (locked_until IS NULL OR locked_until <= ?)',
                  [$n - 86400, $n - 86400, $n, $n, $k, $n])->rowCount() !== 1) return false;
            $c = (int)q('SELECT count FROM login_failures WHERE k = ?', [$k])->fetchColumn();
            $until = $c >= LOCK_FAILURES ? LOCKED_FOR_GOOD
                   : ($c >= FREE_FAILURES ? $n + min(MAX_WAIT, 60 * 2 ** ($c - FREE_FAILURES)) : null);
            q('UPDATE login_failures SET locked_until = ? WHERE k = ?', [$until, $k]);
            return true;
        });
        if ($claimed) return null;
        $f = q('SELECT locked_until FROM login_failures WHERE k = ?', [$k])->fetch();
        if ($f) {
            $until = (int)$f['locked_until'];
            if ($until === LOCKED_FOR_GOOD) return [423, 'locked', 0];
            if ($until > now()) return [429, 'wait', $until - now()];
            continue;   // the wait ended in between: claim again
        }
        try {
            q('INSERT INTO login_failures (k, count, first_at, last_at, locked_until) VALUES (?, 0, ?, ?, NULL)', [$k, now(), now()]);
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000') throw $e;   // a parallel attempt made the row first: claim it
        }
    }
    throw new RuntimeException('login_failures: the attempt could not be counted');
}

function gate_or_fail(string $k): void {
    $g = claim_attempt($k);
    if ($g) fail($g[0], $g[1], null, $g[1] === 'wait' ? ['retry_after' => $g[2]] : []);
}

function clear_failures(string $k): void { q('DELETE FROM login_failures WHERE k = ?', [$k]); }

/* A signed-in person's password, inside the same attempt limits as login: a wrong one stays counted and is
   answered 403 bad_password on $field, so a stolen session cannot try passwords any faster than login. */
function check_password(array $acc, string $p, string $field): void {
    $k = fail_key((string)$acc['email']);
    gate_or_fail($k);
    if (!password_verify($p, (string)$acc['password_hash'])) fail(403, 'bad_password', $field);
    clear_failures($k);
}

/* ---------- rate limits (as Copius) ---------- */

/* REMOTE_ADDR and nothing else: a forwarded header is the client's to set. IPv6 counts by its /56. */
function ip_key(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $bin = @inet_pton($ip);
    if ($bin !== false && strlen($bin) === 16) $ip = inet_ntop(substr($bin, 0, 7) . str_repeat("\0", 9)) . '/56';
    return $ip;
}

function rk(string $s): string { global $KEY; return substr(hash_hmac('sha256', $s, $KEY), 0, 32); }

/* Limits x10 on the rig only, where the probes run many requests from one address. */
function lim(int $n): int { return rig() ? $n * 10 : $n; }

function hits(string $k, int $window): int {
    return (int)q('SELECT COUNT(*) FROM rate_events WHERE k = ? AND at > ?', [$k, now() - $window])->fetchColumn();
}

function hit(string $k): int { q('INSERT INTO rate_events (k, at) VALUES (?, ?)', [$k, now()]); return (int)db()->lastInsertId(); }

function untake(int $id): void { q('DELETE FROM rate_events WHERE id = ?', [$id]); }

/* One event under its limits ([window seconds => most events]): recorded first, then counted with every other,
   so parallel requests cannot all pass on one count. The event's id, or null over a limit, where the event is
   taken back so the table grows only with what the limits let through. */
function take(string $k, array $limits): ?int {
    $id = hit($k);
    foreach ($limits as $window => $max) {
        if (hits($k, $window) > $max) { untake($id); return null; }
    }
    return $id;
}

/* The per-IP limit of one endpoint: a 429 over it. Off when config.php says REMOTE_ADDR is the balancer. */
function ip_limit(string $name, int $per15, int $per24h): void {
    if (!cfg('per_ip_limits')) return;
    if (take(rk($name . ':' . ip_key()), [900 => lim($per15), 86400 => lim($per24h)]) === null) fail(429, 'too_many');
}

/* Whether one more mail may go to this address: 3 an hour and 10 a day per address, for every endpoint, and
   the site's hourly cap under the mailbox's SMTP quota. The mails a sign-up sends ($reserved false) stop
   MAIL_RESERVE short of that cap, so a flood of sign-ups never keeps a reset link from going out. */
function mail_allowed(string $addr, bool $reserved): bool {
    $a = take(rk('m:' . $addr), [3600 => lim(3), 86400 => lim(10)]);
    if ($a === null) return false;
    $cap = (int)(cfg('mail_site_cap') ?? MAIL_SITE_CAP);
    if (take(rk('mail'), [3600 => $reserved ? $cap : $cap - MAIL_RESERVE]) === null) { untake($a); return false; }
    return true;
}

/* ---------- sessions and the cookie ---------- */

function b64url(string $bin): string { return rtrim(strtr(base64_encode($bin), '+/', '-_'), '='); }

function set_cookie(string $sid): void {
    header('Set-Cookie: ' . COOKIE . '=' . $sid . '; Max-Age=' . SESSION_LIFE . '; Path=/; Secure; HttpOnly; SameSite=Lax', false);
}

function clear_cookie(): void {
    header('Set-Cookie: ' . COOKIE . '=; Max-Age=0; Path=/; Secure; HttpOnly; SameSite=Lax', false);
}

/* A new session for the account; the id lives only in the cookie, the table holds its SHA-256. */
function new_session(int $account): string {
    $sid = b64url(random_bytes(32));
    q('INSERT INTO sessions (id_hash, account_id, created_at, last_seen_at, expires_at) VALUES (?, ?, ?, ?, ?)',
      [hash('sha256', $sid), $account, now(), now(), now() + SESSION_LIFE]);
    q('UPDATE accounts SET last_seen_at = ?, inactive_warned_at = NULL WHERE id = ?', [now(), $account]);
    return $sid;
}

/* The session behind the cookie, with its account, or null. Its last use, its expiry and the account's last
   use move at most once an hour, and the cookie is sent again when they do. */
function current_session(): ?array {
    $sid = $_COOKIE[COOKIE] ?? '';
    if (!is_string($sid) || !preg_match('/^[A-Za-z0-9_-]{43}$/', $sid)) return null;
    $s = q('SELECT s.id_hash, s.account_id, s.last_seen_at, a.id, a.email, a.kind, a.lang, a.password_hash
            FROM sessions s JOIN accounts a ON a.id = s.account_id WHERE s.id_hash = ? AND s.expires_at > ?',
           [hash('sha256', $sid), now()])->fetch();
    if (!$s) return null;
    if (now() - (int)$s['last_seen_at'] >= SEEN_EVERY) {
        q('UPDATE sessions SET last_seen_at = ?, expires_at = ? WHERE id_hash = ?', [now(), now() + SESSION_LIFE, $s['id_hash']]);
        q('UPDATE accounts SET last_seen_at = ?, inactive_warned_at = NULL WHERE id = ?', [now(), $s['account_id']]);
        set_cookie($sid);
    }
    return $s;
}

function require_session(): array {
    $s = current_session();
    if (!$s) { clear_cookie(); fail(401, 'signed_out'); }
    return $s;
}

/* ---------- links ---------- */

/* A new single-use link token; the table holds its SHA-256. $cancel drops the account's earlier unused links
   of the same purpose (a new reset link cancels the old ones). */
function new_token(int $account, string $purpose, int $life, ?string $newEmail = null, bool $cancel = false): string {
    $t = b64url(random_bytes(32));
    if ($cancel) q('DELETE FROM tokens WHERE account_id = ? AND purpose = ? AND used_at IS NULL', [$account, $purpose]);
    q('INSERT INTO tokens (hash, account_id, purpose, new_email, created_at, expires_at, used_at) VALUES (?, ?, ?, ?, ?, ?, NULL)',
      [hash('sha256', $t), $account, $purpose, $newEmail, now(), now() + $life]);
    return $t;
}

/* Spends a token once: the row it was, or null for unknown, used, expired or another purpose alike. Call
   inside a transaction with what the token authorises. */
function spend_token(string $t, array $purposes): ?array {
    if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $t)) return null;
    $h = hash('sha256', $t);
    $in = implode(', ', array_fill(0, count($purposes), '?'));
    if (q("UPDATE tokens SET used_at = ? WHERE hash = ? AND purpose IN ($in) AND used_at IS NULL AND expires_at > ?",
          array_merge([now(), $h], $purposes, [now()]))->rowCount() !== 1) return null;
    return q('SELECT account_id, purpose, new_email FROM tokens WHERE hash = ?', [$h])->fetch() ?: null;
}

/* ---------- logs (as Copius: one file per web server per day, kept 30 days) ---------- */

function log_file(string $kind): string {
    $host = preg_replace('/[^a-z0-9.-]/', '', strtolower((string)gethostname())) ?: 'host';
    return PRIV . "/logs/$kind-$host-" . date('Y-m-d') . '.log';
}

function log_line(string $kind, string $line): void {
    if (!is_dir(PRIV . '/logs')) @mkdir(PRIV . '/logs', 0700, true);
    @file_put_contents(log_file($kind), date('c') . ' ' . $line . "\n", FILE_APPEND | LOCK_EX);
}

/* Time, endpoint, outcome and duration, and at most the account id: never a password, a token, a session id,
   an address or an IP. */
function log_api(string $outcome, ?int $account = null): void {
    global $T0;
    log_line('auth', basename($_SERVER['SCRIPT_NAME'] ?? '?', '.php') . ' ' . $outcome .
        ' ' . (int)round((microtime(true) - $T0) * 1000) . 'ms' .
        ($account !== null ? " account=$account" : '') . (cfg('probe_mode') ? ' probe' : ''));
}

function log_php(string $msg): void { log_line('php', $msg); }

function prune_logs(): void {
    $cut = date('Y-m-d', now() - 30 * 86400);
    foreach (glob(PRIV . '/logs/*-????-??-??.log') ?: [] as $f) {
        if (preg_match('/-(\d{4}-\d{2}-\d{2})\.log$/', $f, $m) && $m[1] < $cut) @unlink($f);
    }
}

/* ---------- clean-up ---------- */

/* Everything an account owns, at once. Its address's failure counter stays (it belongs to the address, holds
   no address, and goes by itself 24 h after its last failure unless locked): removing it would let a deleted
   account answer unlike an address that never had one. */
function delete_account(int $id): void {
    atomic(function () use ($id) {
        q('DELETE FROM sessions WHERE account_id = ?', [$id]);
        q('DELETE FROM tokens WHERE account_id = ?', [$id]);
        q('DELETE FROM accounts WHERE id = ?', [$id]);
    });
}

/* The deletions: rate events after 24 h, which the limits count; failure counters 24 h after their last
   failure, unless locked (a streak that old starts again anyway, so nothing answers differently); used or
   expired links 23 h after (so with an hourly run none is kept past 24 h); expired sessions; accounts left
   unconfirmed for 7 days; accounts unused for 24 months once their warning went out 28 days before
   (_purge-auth.php sends it at 23), or unused for 24 months and 30 days whether or not it could go. Run
   hourly by the OVH task, and by the endpoints after their answer at most once an hour in between. */
function purge(): bool {
    try {
        $n = now();
        q('DELETE FROM rate_events WHERE at < ?', [$n - 86400]);
        q('DELETE FROM login_failures WHERE last_at < ? AND (locked_until IS NULL OR locked_until < ?)', [$n - 86400, LOCKED_FOR_GOOD]);
        q('DELETE FROM tokens WHERE (used_at IS NOT NULL AND used_at < ?) OR expires_at < ?', [$n - 23 * 3600, $n - 23 * 3600]);
        q('DELETE FROM sessions WHERE expires_at < ?', [$n]);
        $gone = q('SELECT id FROM accounts WHERE email_verified_at IS NULL AND created_at < ?', [$n - 7 * 86400])->fetchAll(PDO::FETCH_COLUMN);
        $cut = strtotime('-24 months', $n);
        $gone = array_merge($gone, q('SELECT id FROM accounts WHERE last_seen_at < ?
                                      AND ((inactive_warned_at IS NOT NULL AND inactive_warned_at < ?) OR last_seen_at < ?)',
                                     [$cut, $n - 28 * 86400, $cut - 30 * 86400])->fetchAll(PDO::FETCH_COLUMN));
        foreach ($gone as $id) delete_account((int)$id);
        prune_logs();
        return true;
    } catch (Throwable $e) {
        log_php('purge: ' . describe($e));
        return false;
    }
}

function purge_soon(): void {
    try {
        $k = rk('purge');
        if (hits($k, 3600) > 0) return;
        hit($k);
    } catch (Throwable $e) {
        log_php('purge: ' . describe($e)); return;
    }
    purge();
}
