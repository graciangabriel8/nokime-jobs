<?php
// Receives one offer from the « Publier une offre » form (publier/index.html, js/jobs.js). Every field is checked
// against the form's own limits, then the offer is appended as one JSON line to <home>/jobs-private/offres.jsonl
// (see _offres.php) and a copy is mailed to contact@nokime.fr. The stored line is the record, read on the private
// page api/compte.php; the mail is a courtesy that may not arrive (DMARC, hosting quota), so it never decides the
// answer, and it leaves after the answer. Lines past thirty days are erased here, on the private page and by the
// daily task (_purge-offres.php), as the legal notice promises. No IP address, user agent or cookie is read or kept.
// Answers JSON: 201 {ok, ref} or 4xx/5xx {ok:false, error, field?}.
// The establishment "__probe__" is the deployment test: checked the same way, filed in offres-probe.jsonl, mailed
// with « [test] » in the subject, under its own daily cap.
// Known limit, accepted with keeping no IP: a script can spend the day's cap, and real establishments are then refused
// until midnight; js/jobs.js hands them the same offer as a mail, so nothing is lost. Runs on PHP 7.4+.
ini_set('display_errors', '0');   // never print a warning (it would show server paths)
header('Cache-Control: no-store');
header('Content-Type: application/json; charset=utf-8');
header('X-Robots-Tag: noindex');
require_once __DIR__ . '/_offres.php';

const TO = 'contact@nokime.fr';
const MAX_PER_DAY = 40;            // offers accepted per day for the whole site (robots' ones included)
const MAX_PER_EMAIL_DAY = 5;       // per contact address per day
const MAX_PROBES_PER_DAY = 5;      // deployment tests per day: each one sends a mail

function answer($code, $body) { http_response_code($code); echo json_encode($body, JSON_UNESCAPED_UNICODE); }
function fail($code, $error, $field = null) { answer($code, $field === null ? array('ok' => false, 'error' => $error) : array('ok' => false, 'error' => $error, 'field' => $field)); exit; }
function len($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : preg_match_all('/./us', $s); }
function cut($s, $n) { return preg_match('/^.{0,' . (int) $n . '}/us', $s, $m) ? $m[0] : ''; }   // whole characters, with or without mbstring

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { fail(405, 'method'); }
// A form on another site cannot post here through a visitor's browser. A request with no Origin (a script) is let
// through: the checks and caps below are what stop abuse.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== 'https://jobs.nokime.fr') { fail(403, 'origin'); }

// One text field: a string, valid UTF-8, no control characters and no invisible or direction-changing ones (they
// could disguise a name on the private page and in the mail), trimmed, within its length. A required field must
// hold at least one letter or digit. Single-line fields refuse line breaks.
function field($name, $max, $required, $multiline = false) {
    $v = $_POST[$name] ?? '';
    if (!is_string($v)) { fail(400, 'invalid', $name); }
    $v = trim(str_replace("\r\n", "\n", $v));
    if (!preg_match('//u', $v)) { fail(400, 'invalid', $name); }
    if (preg_match($multiline ? '/[\x00-\x09\x0B-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $v)) { fail(400, 'invalid', $name); }
    if (preg_match('/[\x{80}-\x{9F}\x{200B}\x{200E}\x{200F}\x{2028}-\x{202E}\x{2060}-\x{2069}\x{FEFF}]/u', $v)) { fail(400, 'invalid', $name); }   // the joiners U+200C/D stay: emoji like 👩‍🍳 need them
    if ($required && !preg_match('/[\p{L}\p{N}]/u', $v)) { fail(400, 'missing', $name); }
    if (len($v) > $max) { fail(400, 'too_long', $name); }
    return $v;
}
function choice($name, $allowed, $required) {
    $v = $_POST[$name] ?? '';
    if ($v === '' && !$required) { return ''; }
    if (!is_string($v) || !in_array($v, $allowed, true)) { fail(400, $v === '' ? 'missing' : 'invalid', $name); }
    return $v;
}
function day($name) {
    $v = field($name, 10, true);
    $d = DateTime::createFromFormat('!Y-m-d', $v, new DateTimeZone('Europe/Paris'));
    if (!$d || $d->format('Y-m-d') !== $v) { fail(400, 'invalid', $name); }
    return $d;
}

$tz = new DateTimeZone('Europe/Paris');
$now = new DateTime('now', $tz);
$o = array();
$o['kind'] = choice('kind', array('stage', 'alternance', 'saison'), true);
$o['minors'] = $o['kind'] === 'stage' ? choice('minors', array('oui', 'non'), true) : '';   // asked for an internship only
$o['metier'] = choice('metier', array('cuisine', 'salle', 'hebergement', 'spa'), true);
$o['restaurant'] = field('restaurant', 120, true);
$o['city'] = field('city', 80, true);
$o['dept'] = strtoupper(field('dept', 3, true));
if (!preg_match('/^(0[1-9]|[1-8]\d|9[0-5]|2[AB]|97[1-6])$/D', $o['dept'])) { fail(400, 'invalid', 'dept'); }   // metropolitan, Corsica, overseas
$o['role'] = field('role', 120, true);
$start = day('start');
$end = day('end');
// No offer that started over a month ago or starts in more than two years, none ending before it starts, none longer
// than three years (an apprenticeship's longest)
if ($start < (clone $now)->setTime(0, 0)->modify('-31 days') || $start > (clone $now)->modify('+2 years')) { fail(400, 'invalid', 'start'); }
if ($end < $start || $end > (clone $start)->modify('+3 years')) { fail(400, 'invalid', 'end'); }
$o['start'] = $start->format('Y-m-d');
$o['end'] = $end->format('Y-m-d');
$o['hours'] = field('hours', 2, false);
if ($o['hours'] !== '' && (!preg_match('/^\d{1,2}$/D', $o['hours']) || (int) $o['hours'] < 1 || (int) $o['hours'] > 48)) { fail(400, 'invalid', 'hours'); }   // the form's 1 to 48, the legal weekly maximum
$o['pay'] = field('pay', 120, false);
$o['payHidden'] = ($_POST['payHidden'] ?? '') === 'on';
$o['housing'] = choice('housing', array('oui', 'non'), true);
$o['contactName'] = field('contactName', 80, true);
$o['email'] = field('email', 120, true);
if (!filter_var($o['email'], FILTER_VALIDATE_EMAIL) || strpbrk($o['email'], '",;<> ') !== false) { fail(400, 'invalid', 'email'); }   // a plain address: no quoted part, no list
$o['phone'] = field('phone', 30, false);
if ($o['phone'] !== '' && !preg_match('/^[0-9 +().-]{6,30}$/D', $o['phone'])) { fail(400, 'invalid', 'phone'); }
$o['text'] = field('text', 300, false, true);
if (($_POST['lawful'] ?? '') !== 'on') { fail(400, 'missing', 'lawful'); }
// The hidden « Site web » field: a person never sees it, a robot fills it. Autofill could too, so the offer is kept,
// flagged, and not mailed; it counts in the caps like any other.
$robot = ($_POST['website'] ?? '') !== '';
if ($robot) { $o['hp'] = true; }

$probe = $o['restaurant'] === '__probe__';
$file = $probe ? 'offres-probe.jsonl' : 'offres.jsonl';
$ref = bin2hex(random_bytes(4));
$entry = array('ref' => $ref, 'at' => $now->format('Y-m-d\TH:i:sP')) + $o;
$line = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($line === false) { fail(500, 'storage'); }

$lock = offres_lock(LOCK_EX);
if (!$lock) { fail(500, 'storage'); }
if (!offres_purge($file)) { offres_unlock($lock); fail(500, 'storage'); }
$today = $now->format('Y-m-d'); $mailKey = strtolower($o['email']); $todayAll = 0; $todayMail = 0;
foreach (offres_read($file) as $e) {
    if (substr($e['at'], 0, 10) !== $today) { continue; }
    $todayAll++;
    if (isset($e['email']) && strtolower((string) $e['email']) === $mailKey) { $todayMail++; }
}
if ($probe ? $todayAll >= MAX_PROBES_PER_DAY : ($todayAll >= MAX_PER_DAY || $todayMail >= MAX_PER_EMAIL_DAY)) { offres_unlock($lock); fail(429, 'too_many'); }
$path = offres_dir() . '/' . $file;
clearstatcache(true, $path);
$pre = file_exists($path) ? filesize($path) : 0;
if ($pre + strlen($line) + 2 > OFFRES_MAX_BYTES) { offres_unlock($lock); fail(503, 'full'); }
// Appended, so the other offers are never rewritten. A last line left without its line break (a write cut short
// before) gets one first; a write cut short now is taken back to the size before it, so nothing is glued together.
$fh = @fopen($path, 'c+');
$ok = false;
if ($fh) {
    $sep = '';
    if ($pre > 0 && fseek($fh, $pre - 1) === 0 && fread($fh, 1) !== "\n") { $sep = "\n"; }
    fseek($fh, 0, SEEK_END);
    $data = $sep . $line . "\n";
    $ok = fwrite($fh, $data) === strlen($data) && fflush($fh);
    if (!$ok) { ftruncate($fh, $pre); }
    fclose($fh);
}
@chmod($path, 0600);
offres_unlock($lock);
if (!$ok) { fail(500, 'storage'); }

// The answer leaves now; the mail after it, so a slow mail server never makes a stored offer look lost.
ignore_user_abort(true);
$out = json_encode(array('ok' => true, 'ref' => $ref));
http_response_code(201);
header('Content-Length: ' . strlen($out));
header('Connection: close');   // without PHP-FPM, this is what lets the browser stop waiting before the mail
echo $out;
if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); } else { while (ob_get_level() > 0) { @ob_end_flush(); } @flush(); }
if ($robot) { exit; }

// The copy by mail, in the words of the form. Every header value is fixed or a checked single line.
$kinds = array('stage' => 'Stage', 'alternance' => 'Alternance', 'saison' => 'Saison');
$metiers = array('cuisine' => 'Cuisine', 'salle' => 'Salle', 'hebergement' => 'Hébergement', 'spa' => 'Spa');
$lines = array(
    'Type' => $kinds[$o['kind']], 'Métier' => $metiers[$o['metier']], 'Établissement' => $o['restaurant'], 'Ville' => $o['city'],
    'Département' => $o['dept'], 'Poste' => $o['role'], 'Début' => $o['start'], 'Fin' => $o['end'], 'Heures par semaine' => $o['hours'],
    'Rémunération' => $o['payHidden'] || $o['pay'] === '' ? 'non communiquée' . ($o['pay'] !== '' ? ' (indiquée : ' . $o['pay'] . ')' : '') : $o['pay'],
    'Logement' => $o['housing'] === 'oui' ? 'logé' : 'non logé',
);
if ($o['kind'] === 'stage') { $lines['Mineurs'] = $o['minors'] === 'oui' ? 'acceptés' : 'non acceptés'; }
$lines += array('Contact' => $o['contactName'], 'Email' => $o['email'], 'Téléphone' => $o['phone']);
$body = "Texte saisi par un visiteur du site, non vérifié.\n\n";
foreach ($lines as $k => $v) { $body .= $k . ' : ' . $v . "\n"; }
$body .= "\nDescription :\n" . $o['text'] . "\n\nRéférence " . $ref . ', reçue le ' . $now->format('d/m/Y à H:i') . " depuis le formulaire de Nokime Jobs.\n";
$name = len($o['restaurant']) > 80 ? cut($o['restaurant'], 80) . '…' : $o['restaurant'];
$subject = ($probe ? '[test] ' : '') . "Nokime Jobs — offre\u{202F}: " . $name;
if (function_exists('mb_encode_mimeheader')) { mb_internal_encoding('UTF-8'); $subject = mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"); }
else { $subject = '=?UTF-8?B?' . base64_encode($subject) . '?='; }
$headers = "From: Nokime Jobs <" . TO . ">\r\nReply-To: " . $o['email'] . "\r\nMIME-Version: 1.0\r\n"
    . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64";
@mail(TO, $subject, chunk_split(base64_encode($body)), $headers, '-f' . TO);
