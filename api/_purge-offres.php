<?php
// The daily task (OVH scheduled task on this hosting): erases received offers past thirty days even on days with
// no new offer, as the legal notice promises. Command line only; .htaccess also answers 404 to /api/_*.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/_offres.php';
$lock = offres_lock(LOCK_EX);
if (!$lock) { fwrite(STDERR, "offres: lock failed\n"); exit(1); }
$ok = offres_purge('offres.jsonl') && offres_purge('offres-probe.jsonl');
offres_unlock($lock);
if (!$ok) { fwrite(STDERR, "offres: purge failed, files left as they were\n"); exit(1); }
echo 'offres: purge ok ' . date('c') . "\n";   // one line in the task's log, so a run is visible
