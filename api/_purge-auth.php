<?php
/* The hourly job for accounts, run by an OVH scheduled task (jobs/api/_purge-auth.php, hourly) that Gabriel
   creates: the deletions of _auth.php's purge(), whatever the traffic; then the warning mail to accounts unused
   for 23 months, at most 20 tries a run, each marked once sent so it goes once. A warning that could not go is
   tried again a day later, and the accounts behind it are served meanwhile, so no address that keeps failing
   holds up the queue. An account still unused at 24 months, warned at least 31 days before (the mail says « d’ici
   un mois »), goes in a later purge; one whose warning never went goes at 24 months and 30 days. Command line only;
   .htaccess also answers 404 to /api/_*. A failure exits 1 with a line on stderr. OVH disables a task after 10
   failed runs and sends one report a day after that; check the cron log. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/_auth.php';
require __DIR__ . '/_auth-mail.php';

const WARN_PER_RUN = 20;    // tries a run
const WARN_SCAN = 200;      // accounts read a run to find them, past those that failed within the day
$failed = [];
if (!purge()) $failed[] = 'purge';
try {
    $due = q('SELECT id, email, lang FROM accounts WHERE email_verified_at IS NOT NULL AND inactive_warned_at IS NULL AND last_seen_at < ?
              ORDER BY last_seen_at LIMIT ' . WARN_SCAN, [strtotime('-23 months', now())])->fetchAll();
    $tries = 0;
    foreach ($due as $a) {
        if ($tries >= WARN_PER_RUN) break;
        $k = rk('warn_failed:' . $a['id']);
        if (hits($k, 86400) > 0) continue;
        $tries++;
        $out = send_mail('inactive', (string)$a['email'], (string)$a['lang'], site_origin() . '/compte/');
        if ($out === 'sent' || $out === 'sent_retry' || $out === 'sent_file') {
            q('UPDATE accounts SET inactive_warned_at = ? WHERE id = ?', [now(), $a['id']]);
        } else {
            hit($k);
        }
        log_api('inactive_warning ' . $out, (int)$a['id']);
    }
} catch (Throwable $e) {
    log_php('inactive warnings: ' . describe($e));
    $failed[] = 'warnings';
}
if ($failed) {
    log_api('failed ' . implode(',', $failed));
    fwrite(STDERR, 'Nokime Jobs accounts: hourly job failed (' . implode(', ', $failed) . "): see jobs-private/logs/php-*.log\n");
    exit(1);
}
log_api('purged');
echo 'comptes: purge ok ' . date('c') . "\n";   // one line in the task's log, so a run is visible
