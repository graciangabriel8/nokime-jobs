<?php
// The received offers' files, shared by api/offre.php (writes), api/compte.php (reads) and api/_purge-offres.php
// (the daily task). One line of JSON per offer in <home>/jobs-private/offres.jsonl (offres-probe.jsonl for the
// deployment tests). A new offer is appended; the thirty-day erasure rewrites the file into a temporary one and
// renames it over the old, so a failed write never empties the file. Every access holds offres.lock: shared to
// read, exclusive to write. Runs on PHP 7.4+.

const OFFRES_KEEP_DAYS = 30;          // the legal notice promises erasure thirty days after an offer arrives
const OFFRES_MAX_BYTES = 4194304;      // past 4 MB offre.php refuses new offers, and the private page says so

function offres_dir() {
    $dir = dirname(__DIR__, 2) . '/jobs-private';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
    return $dir;
}

// The lock for both files, or null. $mode is LOCK_SH or LOCK_EX; release with offres_unlock().
function offres_lock($mode) {
    $fh = @fopen(offres_dir() . '/offres.lock', 'c+');   // read-write: a shared lock on a write-only handle fails on NFS
    if (!$fh) { return null; }
    @chmod(offres_dir() . '/offres.lock', 0600);
    if (!flock($fh, $mode)) { fclose($fh); return null; }
    return $fh;
}
function offres_unlock($fh) { if ($fh) { flock($fh, LOCK_UN); fclose($fh); } }

function offres_cutoff() {
    return (new DateTime('now', new DateTimeZone('Europe/Paris')))->modify('-' . OFFRES_KEEP_DAYS . ' days')->format('Y-m-d\TH:i:sP');
}

// The kept offers of one file, oldest first: none past thirty days, even if a purge failed. A line that does not
// parse (a write cut short) holds no usable offer.
function offres_read($file) {
    $r = array(); $cutoff = offres_cutoff();
    $fh = @fopen(offres_dir() . '/' . $file, 'r');
    if (!$fh) { return $r; }
    while (($l = fgets($fh)) !== false) {
        $e = json_decode($l, true);
        if (is_array($e) && isset($e['at']) && is_string($e['at']) && $e['at'] >= $cutoff) { $r[] = $e; }
    }
    fclose($fh);
    return $r;
}

// Erase what is past thirty days, and any line that does not parse. Call with the exclusive lock held.
// Rewrites only when something goes; returns false if the rewrite failed (the old file is then untouched).
function offres_purge($file) {
    $path = offres_dir() . '/' . $file;
    if (!file_exists($path)) { return true; }
    $cutoff = offres_cutoff();
    $keep = array(); $drop = 0;
    $fh = @fopen($path, 'r');
    if (!$fh) { return false; }
    while (($l = fgets($fh)) !== false) {
        $e = json_decode($l, true);
        if (is_array($e) && isset($e['at']) && is_string($e['at']) && $e['at'] >= $cutoff) { $keep[] = rtrim($l, "\r\n"); } else { $drop++; }
    }
    fclose($fh);
    if ($drop === 0) { return true; }
    $tmp = $path . '.tmp';
    $data = $keep ? implode("\n", $keep) . "\n" : '';
    if (@file_put_contents($tmp, $data) !== strlen($data)) { @unlink($tmp); return false; }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) { @unlink($tmp); return false; }
    return true;
}
