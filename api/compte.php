<?php
// Private page: the « Postuler » click counts per live offer. Needs ?k=<key>; only the key's SHA-256 is written here.
// Runs on PHP 7.4+.
ini_set('display_errors', '0');   // never print a warning (it would show server paths)
const KEY_HASH = '00b38a17c19de011fa966018758c4a28f8b472986924e15ebc7d92e9f71c7665';

function not_found() { http_response_code(404); header('Cache-Control: no-store'); exit; }

$k = $_GET['k'] ?? '';
if (!is_string($k) || $k === '' || !hash_equals(KEY_HASH, hash('sha256', $k))) { not_found(); }

function h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

$tz = new DateTimeZone('Europe/Paris');
$today = new DateTime('now', $tz);
$d7 = (clone $today)->modify('-6 days')->format('Y-m-d');    // today and the six days before
$d30 = (clone $today)->modify('-29 days')->format('Y-m-d');
$todayS = $today->format('Y-m-d');

function tally($path, $d7, $d30) {
    $c = array();
    $fh = @fopen($path, 'r');
    if (!$fh) { return $c; }
    while (($l = fgets($fh)) !== false) {
        $p = explode(',', rtrim($l, "\r\n"), 2);
        if (count($p) !== 2 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $p[0])) { continue; }
        if (!isset($c[$p[1]])) { $c[$p[1]] = array(0, 0, 0); }
        $c[$p[1]][0]++;
        if ($p[0] >= $d30) { $c[$p[1]][2]++; }
        if ($p[0] >= $d7) { $c[$p[1]][1]++; }
    }
    fclose($fh);
    return $c;
}

$dir = dirname(__DIR__, 2) . '/jobs-private';
$counts = tally($dir . '/postuler.csv', $d7, $d30);
$probe = tally($dir . '/probe.csv', $d7, $d30);
$probeN = isset($probe['__probe__']) ? $probe['__probe__'][0] : 0;
$full = @filesize($dir . '/postuler.csv') > 5 * 1024 * 1024;   // postuler.php stops writing past 5 MB: say so rather than show frozen counts

$rows = array();
$src = @file_get_contents(__DIR__ . '/../js/offers.js', false, null, 0, 2 * 1024 * 1024);
if ($src !== false && preg_match('/window\.NOKIME_JOBS\s*=\s*(\[.*?\]);\s*\n/s', $src, $m)) {
    $offers = json_decode($m[1], true);
    if (is_array($offers)) {
        foreach ($offers as $o) {
            if (!is_array($o) || !empty($o['demo']) || !isset($o['id']) || !is_string($o['id'])) { continue; }
            $x = isset($o['expires']) ? $o['expires'] : (isset($o['end']) ? $o['end'] : '');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $x) || $x < $todayS) { continue; }   // live, as js/jobs.js
            $rows[] = $o;
        }
    }
}
usort($rows, function ($a, $b) { return strcmp(isset($b['published']) ? $b['published'] : '', isset($a['published']) ? $a['published'] : ''); });

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('Referrer-Policy: no-referrer');
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title>Clics sur Postuler — Nokime Jobs</title>
<style>
body{font:16px/1.5 system-ui,sans-serif;color:#17191E;background:#fff;margin:0;padding:24px 16px}
main{max-width:860px;margin:0 auto}
h1{font-size:1.4rem;margin:0 0 4px}
p{margin:4px 0 16px;color:#555}
table{border-collapse:collapse;width:100%}
th,td{text-align:left;padding:8px 10px;border-bottom:1px solid #ddd;vertical-align:top}
th{font-size:.8rem;text-transform:uppercase;letter-spacing:.04em;color:#555}
td.n,th.n{text-align:right;font-variant-numeric:tabular-nums}
.zero{color:#B00020;font-weight:600}
.scroll{overflow-x:auto}
</style>
</head>
<body>
<main>
<h1>Clics sur « Postuler »</h1>
<p>Au <?= h($todayS) ?>. Un clic compte pour une offre et un jour ; 7 jours = aujourd’hui et les six précédents, 30 jours de même. Rien n’identifie personne.</p>
<?php if ($full): ?>
<p class="zero">Le fichier des clics dépasse 5 Mo : plus aucun clic n’est compté. Archivez jobs-private/postuler.csv hors du serveur, puis videz-le.</p>
<?php endif; ?>
<?php if (!$rows): ?>
<p>Aucune offre en ligne.</p>
<?php else: ?>
<div class="scroll"><table>
<thead><tr><th>Offre</th><th>Établissement</th><th>Ville</th><th class="n">Total</th><th class="n">7 jours</th><th class="n">30 jours</th></tr></thead>
<tbody>
<?php foreach ($rows as $o): $c = isset($counts[$o['id']]) ? $counts[$o['id']] : array(0, 0, 0); ?>
<tr><td><?= h(isset($o['role']) ? $o['role'] : $o['id']) ?></td><td><?= h(isset($o['restaurant']) ? $o['restaurant'] : '') ?></td><td><?= h(isset($o['city']) ? $o['city'] : '') ?></td><td class="n<?= $c[0] === 0 ? ' zero' : '' ?>"><?= (int) $c[0] ?></td><td class="n"><?= (int) $c[1] ?></td><td class="n"><?= (int) $c[2] ?></td></tr>
<?php endforeach; ?>
</tbody>
</table></div>
<?php endif; ?>
<p>Test du déploiement (<code>__probe__</code>) : <?= (int) $probeN ?></p>
</main>
</body>
</html>
