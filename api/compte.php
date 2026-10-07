<?php
// Private page: the « Postuler » click counts per live offer, and the offers received through the form (api/offre.php).
// The key comes only through the page's own form (POST), never in the address: an address with ?k= lands in the
// host's logs and the browser's history, and the page now shows names, emails and phones. Without a valid key the
// page is that form; a wrong key waits half a second. Only the key's SHA-256 is written here.
// Runs on PHP 7.4+.
ini_set('display_errors', '0');   // never print a warning (it would show server paths)
const KEY_HASH = '00b38a17c19de011fa966018758c4a28f8b472986924e15ebc7d92e9f71c7665';

$k = $_POST['k'] ?? '';
if (!is_string($k) || $k === '' || !hash_equals(KEY_HASH, hash('sha256', $k))) {
    if ($k !== '') { usleep(500000); }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    header('Referrer-Policy: no-referrer');
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Nokime Jobs — privé</title></head>'
        . '<body style="font:16px/1.5 system-ui,sans-serif;margin:0;padding:24px 16px"><form method="post" action="compte.php">'
        . '<label>Clé <input type="password" name="k" autocomplete="current-password" required autofocus></label> <button type="submit">Ouvrir</button>'
        . '</form></body></html>';
    exit;
}

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

// Offers received through the form (_offres.php), newest first: erased past thirty days first, then read under the lock.
require_once __DIR__ . '/_offres.php';
$inbox = array(); $inboxProbe = 0; $inboxFull = false; $inboxRead = false;
$lk = offres_lock(LOCK_EX);
if ($lk) { offres_purge('offres.jsonl'); offres_purge('offres-probe.jsonl'); offres_unlock($lk); }
$lk = offres_lock(LOCK_SH);
if ($lk) {
    $inbox = offres_read('offres.jsonl');
    $inboxProbe = count(offres_read('offres-probe.jsonl'));
    $inboxFull = @filesize(offres_dir() . '/offres.jsonl') > OFFRES_MAX_BYTES - 8192;   // offre.php refuses offers past this
    $inboxRead = true;
    offres_unlock($lk);
}
usort($inbox, function ($a, $b) { return strcmp((string) $b['at'], (string) $a['at']); });
function g($e, $k) { return isset($e[$k]) && is_scalar($e[$k]) ? (string) $e[$k] : ''; }

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
<title>Postuler et offres reçues — Nokime Jobs</title>
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
h2{font-size:1.15rem;margin:36px 0 4px}
.offer{border:1px solid #ddd;border-radius:10px;padding:14px 16px;margin:0 0 14px}
.offer h3{font-size:1rem;margin:0 0 2px}
.offer .when{font-size:.85rem;color:#555;margin:0 0 10px}
.offer dl{display:grid;grid-template-columns:max-content 1fr;gap:4px 14px;margin:0}
.offer dt{color:#555}
.offer dd{margin:0;overflow-wrap:anywhere}
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

<h2>Offres reçues par le formulaire</h2>
<?php if (!$inboxRead): ?>
<p class="zero">Lecture impossible : le fichier des offres n’a pas pu être verrouillé. Rechargez la page ; si cela dure, le dossier jobs-private est à vérifier.</p>
<?php else: ?>
<p>Les trente derniers jours, la plus récente en premier ; le serveur les efface ensuite. Tests du formulaire (<code>__probe__</code>) sur la période : <?= (int) $inboxProbe ?>.</p>
<?php if ($inboxFull): ?>
<p class="zero">Le fichier des offres approche 4 Mo : le formulaire refuse les nouvelles offres. Archivez jobs-private/offres.jsonl hors du serveur, puis videz-le.</p>
<?php endif; ?>
<?php if (!$inbox): ?>
<p>Aucune.</p>
<?php else: foreach ($inbox as $e):
    $kinds = array('stage' => 'Stage', 'alternance' => 'Alternance', 'saison' => 'Saison');
    $pay = g($e, 'pay'); $hidden = !empty($e['payHidden']);
    $rows2 = array(
        'Type' => isset($kinds[g($e, 'kind')]) ? $kinds[g($e, 'kind')] : g($e, 'kind'), 'Métier' => g($e, 'metier'), 'Ville' => g($e, 'city') . ' (' . g($e, 'dept') . ')',
        'Dates' => g($e, 'start') . ' → ' . g($e, 'end'), 'Heures' => g($e, 'hours'),
        'Rémunération' => $pay === '' ? 'non indiquée' : $pay . ($hidden ? ' (à ne pas publier)' : ''), 'Logement' => g($e, 'housing') === 'oui' ? 'logé' : 'non logé',
    );
    if (g($e, 'kind') === 'stage') { $rows2['Mineurs'] = g($e, 'minors') === 'oui' ? 'acceptés' : 'non acceptés'; }
    $rows2 += array('Contact' => g($e, 'contactName'), 'Téléphone' => g($e, 'phone'));
?>
<div class="offer">
<h3><?= h(g($e, 'role')) ?> — <?= h(g($e, 'restaurant')) ?><?php if (!empty($e['hp'])): ?> <span class="zero">(champ caché rempli : robot probable)</span><?php endif; ?></h3>
<p class="when">Reçue le <?= h(substr(g($e, 'at'), 0, 10)) ?> à <?= h(substr(g($e, 'at'), 11, 5)) ?> · référence <?= h(g($e, 'ref')) ?></p>
<dl>
<?php foreach ($rows2 as $k => $v): if ($v === '') { continue; } ?><dt><?= h($k) ?></dt><dd><?= h($v) ?></dd>
<?php endforeach; ?><dt>Courriel</dt><dd><a href="mailto:<?= h(rawurlencode(g($e, 'email'))) ?>"><?= h(g($e, 'email')) ?></a></dd>
<?php if (g($e, 'text') !== ''): ?><dt>Description</dt><dd><?= nl2br(h(g($e, 'text'))) ?></dd><?php endif; ?>
</dl>
</div>
<?php endforeach; endif; endif; ?>
</main>
</body>
</html>
