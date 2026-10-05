<?php
// Counts one click on « Postuler » for one live offer: a line "YYYY-MM-DD,<offer id>" in <home>/jobs-private/postuler.csv.
// Nothing that identifies a person is read or kept (no IP, user agent, referrer, cookie). Always answers 204.
// Runs on PHP 7.4+.
ini_set('display_errors', '0');   // never print a warning (it would show server paths)
header('Cache-Control: no-store');
http_response_code(204);

const MAX_BYTES = 5 * 1024 * 1024;   // the file stops growing past 5 MB
const MAX_PER_DAY = 500;             // per offer per day, checked exactly while appending under the lock

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { exit; }
$id = $_POST['o'] ?? '';
if (!is_string($id) || ($id !== '__probe__' && !preg_match('/^[a-z0-9-]{1,64}$/D', $id))) { exit; }   // the probe id is the one exception to the pattern

$file = 'postuler.csv';
if ($id === '__probe__') {
    $file = 'probe.csv';
} else {
    $src = @file_get_contents(__DIR__ . '/../js/offers.js', false, null, 0, 2 * 1024 * 1024);   // read at most 2 MB; a list cut off there fails the match below: nothing counted
    if ($src === false || !preg_match('/window\.NOKIME_JOBS\s*=\s*(\[.*?\]);\s*\n/s', $src, $m)) { exit; }
    $offers = json_decode($m[1], true);
    if (!is_array($offers)) { exit; }
    $ok = false;
    foreach ($offers as $o) {
        if (is_array($o) && ($o['id'] ?? null) === $id && empty($o['demo'])) { $ok = true; break; }
    }
    if (!$ok) { exit; }
}

$dir = dirname(__DIR__, 2) . '/jobs-private';
if (!is_dir($dir)) { @mkdir($dir, 0700, true); }
$path = $dir . '/' . $file;
$new = !file_exists($path);
$fh = @fopen($path, 'c+');
if (!$fh) { exit; }
if ($new) { @chmod($path, 0600); }
if (flock($fh, LOCK_EX)) {
    $day = (new DateTime('now', new DateTimeZone('Europe/Paris')))->format('Y-m-d');
    $line = $day . ',' . $id;
    $size = (int) fstat($fh)['size'];
    if ($size <= MAX_BYTES) {
        $n = 0;
        rewind($fh);
        while (($l = fgets($fh)) !== false) {
            if (rtrim($l, "\r\n") === $line) { $n++; }
        }
        if ($n < MAX_PER_DAY) {
            fseek($fh, 0, SEEK_END);
            fwrite($fh, $line . "\n");
            fflush($fh);
        }
    }
    flock($fh, LOCK_UN);
}
fclose($fh);
