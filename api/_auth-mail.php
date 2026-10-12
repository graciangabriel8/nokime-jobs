<?php
/* The account mails and the SMTP client that carries them. Copied from Copius's api/_mail.php (hand-written,
   nothing downloaded) and adapted: Nokime Jobs <comptes@nokime.fr>, no banner, no attachment, seven mails.
   - implicit TLS to smtp.mail.ovh.net:465, certificate and name checked, never a plaintext fallback;
   - AUTH only inside that TLS, and the password in no log and no error string;
   - every reply code checked, the first unexpected one ends the session;
   - CR and LF refused in every header value before anything is written;
   - text and HTML parts quoted-printable UTF-8 with CRLF endings, the whole message dot-stuffed;
   - a timeout on the connect and on each read, inside the request's time.
   On the rig (transport = file in config.php) the message is written to jobs-private/outbox instead. */
declare(strict_types=1);

/* One set of words per mail and language; the text and HTML parts are both built from it. "lead" is the body
   before the button; "notes" go small, after it (Gabriel's rule for mandatory notices). */
const MAIL_TEXT = [
    'fr' => [
        'confirm' => [
            'subject' => "Confirmez votre adresse pour Nokime Jobs",
            'lead' => ["Bienvenue sur Nokime Jobs. Un clic, et votre compte est prêt."],
            'button' => "Confirmer mon adresse",
            'notes' => ["Le lien marche pendant 24 heures.",
                        "Vous n’avez pas créé de compte\u{202F}? Ignorez ce message\u{202F}: sans confirmation, le compte est supprimé au bout de 7 jours."],
        ],
        'exists' => [
            'subject' => "Quelqu’un a voulu créer un compte avec votre adresse",
            'lead' => ["On vient de nous demander un compte Nokime Jobs avec votre adresse. Vous en avez déjà un, alors rien n’a changé.",
                       "C’était vous\u{202F}? Connectez-vous. Mot de passe oublié\u{202F}? Choisissez-en un nouveau."],
            'button' => "Me connecter",
            'second' => "Choisir un nouveau mot de passe",
            'notes' => ["Ce n’était pas vous\u{202F}? Ignorez ce message\u{202F}: votre compte n’a pas bougé."],
        ],
        'reset' => [
            'subject' => "Votre lien pour changer de mot de passe",
            'lead' => ["Voici votre lien pour choisir un nouveau mot de passe Nokime Jobs."],
            'button' => "Choisir mon mot de passe",
            'notes' => ["Il marche une seule fois, pendant une heure. Une fois le mot de passe changé, toutes vos sessions se ferment.",
                        "Vous n’avez rien demandé\u{202F}? Ignorez ce message\u{202F}: votre mot de passe ne change pas."],
        ],
        'change_new' => [
            'subject' => "Confirmez votre nouvelle adresse",
            'lead' => ["Vous voulez utiliser cette adresse pour votre compte Nokime Jobs. Un clic pour confirmer."],
            'button' => "Confirmer cette adresse",
            'notes' => ["Le lien marche pendant 24 heures. Tant qu’il n’a pas servi, rien ne change.",
                        "Vous n’avez rien demandé\u{202F}? Ignorez ce message."],
        ],
        'change_old' => [
            'subject' => "Changement d’adresse demandé sur votre compte",
            'lead' => ["On nous a demandé de changer l’adresse de votre compte Nokime Jobs. Rien ne change tant que le lien envoyé à la nouvelle adresse n’a pas servi.",
                       "Ce n’était pas vous\u{202F}? Changez votre mot de passe tout de suite."],
            'button' => "Changer mon mot de passe",
            'notes' => ["C’était vous\u{202F}? Il n’y a rien d’autre à faire ici."],
        ],
        'goodbye' => [
            'subject' => "Votre compte Nokime Jobs est supprimé",
            'lead' => ["C’est fait\u{202F}: votre compte est supprimé, avec tout ce qui s’y rattachait.",
                       "Les offres restent ouvertes à tous, sans compte. Merci, et bonne route."],
            'button' => "Voir les offres",
            'notes' => ["Vous n’avez rien demandé\u{202F}? Écrivez-nous à contact@nokime.fr."],
        ],
        'inactive' => [
            'subject' => "Votre compte Nokime Jobs sera bientôt supprimé",
            'lead' => ["Votre compte n’a pas servi depuis presque deux ans. S’il ne sert pas d’ici un mois, on le supprime, comme promis."],
            'button' => "Me connecter",
            'notes' => ["Vous n’en avez plus besoin\u{202F}? Ne faites rien\u{202F}: il sera supprimé tout seul."],
        ],
        'hello' => "Bonjour,",
        'copy' => "Le bouton ne s’ouvre pas\u{202F}? Copiez cette adresse dans votre navigateur\u{202F}:",
        'bye' => "À bientôt sur Nokime Jobs.",
    ],
    'en' => [
        'confirm' => [
            'subject' => "Confirm your address for Nokime Jobs",
            'lead' => ["Welcome to Nokime Jobs. One click, and your account is ready."],
            'button' => "Confirm my address",
            'notes' => ["The link works for 24 hours.",
                        "Didn’t create an account? Ignore this email: without confirmation, the account is deleted after 7 days."],
        ],
        'exists' => [
            'subject' => "Someone tried to create an account with your address",
            'lead' => ["We were just asked for a Nokime Jobs account with your address. You already have one, so nothing has changed.",
                       "Was it you? Sign in. Forgot your password? Choose a new one."],
            'button' => "Sign me in",
            'second' => "Choose a new password",
            'notes' => ["Wasn’t you? Ignore this email: your account hasn’t moved."],
        ],
        'reset' => [
            'subject' => "Your link to change your password",
            'lead' => ["Here is your link to choose a new Nokime Jobs password."],
            'button' => "Choose my password",
            'notes' => ["It works once, within one hour. Once the password is changed, all your sessions close.",
                        "Didn’t ask for this? Ignore this email: your password stays as it is."],
        ],
        'change_new' => [
            'subject' => "Confirm your new address",
            'lead' => ["You want to use this address for your Nokime Jobs account. One click to confirm."],
            'button' => "Confirm this address",
            'notes' => ["The link works for 24 hours. Until it is used, nothing changes.",
                        "Didn’t ask for this? Ignore this email."],
        ],
        'change_old' => [
            'subject' => "Address change requested on your account",
            'lead' => ["We were asked to change the address of your Nokime Jobs account. Nothing changes until the link sent to the new address is used.",
                       "Wasn’t you? Change your password right away."],
            'button' => "Change my password",
            'notes' => ["Was it you? There is nothing else to do here."],
        ],
        'goodbye' => [
            'subject' => "Your Nokime Jobs account is deleted",
            'lead' => ["Done: your account is deleted, with everything that came with it.",
                       "The offers stay open to everyone, no account needed. Thank you, and all the best."],
            'button' => "See the offers",
            'notes' => ["Didn’t ask for this? Write to us at contact@nokime.fr."],
        ],
        'inactive' => [
            'subject' => "Your Nokime Jobs account will be deleted soon",
            'lead' => ["Your account hasn’t been used for almost two years. If it isn’t used within a month, we delete it, as promised."],
            'button' => "Sign me in",
            'notes' => ["Don’t need it any more? Do nothing: it will be deleted on its own."],
        ],
        'hello' => "Hello,",
        'copy' => "Button not opening? Copy this address into your browser:",
        'bye' => "See you soon on Nokime Jobs.",
    ],
];

/* An English account's links carry lang=en, so the page opens in the language of the mail it came from
   (site.js reads it; confirm.php passes it on to its redirect). */
function with_lang(string $url, string $lang): string {
    if ($lang !== 'en') return $url;
    $hash = strpos($url, '#');
    $base = $hash === false ? $url : substr($url, 0, $hash);
    return $base . (strpos($base, '?') === false ? '?' : '&') . 'lang=en' . ($hash === false ? '' : substr($url, $hash));
}

function header_safe(string ...$values): bool {
    foreach ($values as $v) if (preg_match('/[\r\n\0]/', $v)) return false;
    return true;
}

/* RFC 2047 for anything that is not plain ASCII. */
function mime_word(string $s): string {
    return preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function mime_parts(string $boundary, array $parts): string {
    return "--$boundary\r\n" . implode("\r\n--$boundary\r\n", $parts) . "\r\n--$boundary--\r\n";
}

/* One mail, or null when a header value carries a line break or the address is not plain ASCII.
   $link is the button's; $second, when the mail has one, the second link's. */
function mail_message(string $kind, string $to, string $lang, string $link, ?string $second = null): ?array {
    $lang = isset(MAIL_TEXT[$lang]) ? $lang : 'fr';
    $all = MAIL_TEXT[$lang];
    $t = $all[$kind];
    $from = (string)cfg('from'); $name = (string)cfg('from_name'); $reply = (string)cfg('reply_to');
    if (!header_safe($to, $from, $name, $reply, $t['subject'], $link, (string)$second)) return null;
    if (!preg_match('/^[\x21-\x7e]+@[\x21-\x7e]+$/', $to) || strpbrk($to, '<>,;"()[]\\') !== false) return null;
    $colon = $lang === 'fr' ? "\u{202F}: " : ': ';
    $text = implode("\n\n", array_merge([$all['hello']], $t['lead'], [$t['button'] . $colon . $link],
        $second !== null ? [$t['second'] . $colon . $second] : [], $t['notes'], [$all['bye'], 'Nokime Jobs · ' . ORIGIN . '/ · contact@nokime.fr'])) . "\n";
    $qp = function (string $s): string { return quoted_printable_encode(str_replace("\n", "\r\n", $s)); };
    $id = bin2hex(random_bytes(8));   // "=_" never occurs in quoted-printable, so no part can hold the boundary
    $body = mime_parts("=_a$id", [
        "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . $qp($text),
        "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" .
            $qp(mail_html($all, $t, $lang, $link, $second)),
    ]);
    return [
        'to' => $to,
        'from' => $from,
        'subject' => mime_word($t['subject']),
        'headers' => [
            'Date: ' . date('r'),
            'From: ' . mime_word($name) . " <$from>",
            "Reply-To: $reply",
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@nokime.fr>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"=_a$id\"",
            'Auto-Submitted: auto-generated',
        ],
        'body' => $body,
    ];
}

/* The HTML part: tables and inline styles, the only layout every mail app keeps; le passe bleu's paper, ink,
   steel and one blue, in the fonts every system has. Every word is escaped. */
function mail_html(array $all, array $t, string $lang, string $link, ?string $second): string {
    $e = function (string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); };
    $l = $e($link);
    $sans = 'font-family:Arial,Helvetica,sans-serif';
    $lead = '';
    foreach ($t['lead'] as $p) $lead .= '<p style="margin:0 0 14px">' . $e($p) . '</p>';
    $notes = '';
    foreach ($t['notes'] as $p) $notes .= '<p style="margin:0 0 8px">' . $e($p) . '</p>';
    $more = $second !== null
        ? '<p style="margin:18px 0 0;font-size:15px"><a href="' . $e($second) . '" style="color:#17191E;text-decoration:underline">' . $e($t['second']) . '</a></p>' : '';
    $origin = $e(ORIGIN);
    return <<<HTML
<!doctype html>
<html lang="$lang"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>{$e($t['subject'])}</title></head>
<body style="margin:0;padding:0;background:#E7EAED">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#E7EAED"><tr><td align="center" style="padding:24px 12px">
<!--[if mso]><table role="presentation" width="560" align="center" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;background:#FFFFFF;border:1px solid #D9DDE2;border-radius:12px">
<tr><td style="padding:28px 32px 0;$sans;font-size:20px;font-weight:700;color:#17191E">nokime <span style="font-weight:800">Jobs</span></td></tr>
<tr><td style="padding:22px 32px 4px;$sans;font-size:16px;line-height:1.55;color:#17191E">
<p style="margin:0 0 14px">{$e($all['hello'])}</p>
$lead
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin-top:10px"><tr><td bgcolor="#2038D5" style="border-radius:999px;background:#2038D5;mso-padding-alt:13px 26px">
<a href="$l" style="display:inline-block;padding:13px 26px;$sans;font-size:16px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:999px">{$e($t['button'])}</a>
</td></tr></table>
$more
</td></tr>
<tr><td style="padding:22px 32px 0;$sans;font-size:13px;line-height:1.5;color:#5A606B">
$notes
<p style="margin:8px 0 4px">{$e($all['copy'])}</p>
<p style="margin:0;word-break:break-all"><a href="$l" style="color:#5A606B">$l</a></p>
</td></tr>
<tr><td style="padding:22px 32px 28px;$sans;font-size:16px;color:#17191E">{$e($all['bye'])}</td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->
<p style="margin:16px 0 0;$sans;font-size:12px;color:#5A606B">Nokime Jobs · <a href="$origin/" style="color:#5A606B">jobs.nokime.fr</a> · <a href="mailto:contact@nokime.fr" style="color:#5A606B">contact@nokime.fr</a></p>
</td></tr></table>
</body></html>
HTML;
}

/* One SMTP session. Null when the server took the message, else a short reason (a reply code or a stage
   name) that never holds a secret. */
function smtp_send(array $m, float $deadline): ?string {
    $s = cfg('smtp');
    $left = function () use ($deadline): float { return $deadline - microtime(true); };
    if ($left() < 5) return 'no_time';
    $tls = ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $s['host'],
            'allow_self_signed' => false, 'SNI_enabled' => true];
    $fp = @stream_socket_client('ssl://' . $s['host'] . ':' . (int)$s['port'], $errno, $errstr,
        min(10.0, $left()), STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => $tls]));
    if (!$fp) return 'connect';
    $say = function (?string $line, array $ok) use ($fp, $left): ?string {
        if ($line !== null && @fwrite($fp, $line . "\r\n") === false) return 'write';
        do {
            if ($left() < 1) return 'no_time';
            stream_set_timeout($fp, (int)max(1, min(10, $left())));
            $r = fgets($fp, 1024);
            if ($r === false) return 'read';
        } while (strlen($r) > 3 && $r[3] === '-');
        $code = (int)substr($r, 0, 3);
        return in_array($code, $ok, true) ? null : 'reply_' . $code;
    };
    $data = implode("\r\n", array_merge($m['headers'], ['To: <' . $m['to'] . '>', 'Subject: ' . $m['subject']])) .
        "\r\n\r\n" . $m['body'];
    $data = preg_replace('/^\./m', '..', $data);                   // dot-stuffing
    $steps = [
        [null, [220]],
        ['EHLO nokime.fr', [250]],
        ['AUTH LOGIN', [334]],
        [base64_encode($s['user']), [334]],
        [base64_encode($s['pass']), [235]],
        ['MAIL FROM:<' . $m['from'] . '>', [250]],
        ['RCPT TO:<' . $m['to'] . '>', [250, 251]],
        ['DATA', [354]],
        [$data . "\r\n.", [250]],
    ];
    $err = null;
    foreach ($steps as $i => [$line, $ok]) {
        $err = $say($line, $ok);
        if ($err !== null) { $err = 'step' . $i . '_' . $err; break; }
    }
    if ($err === null) $say('QUIT', [221]);
    fclose($fp);
    return $err;
}

/* The rig's transport: the message goes to a file in jobs-private/outbox, never anywhere else. */
function mail_to_file(array $m): bool {
    $dir = PRIV . '/outbox';
    if (!is_dir($dir)) @mkdir($dir, 0700);
    return (bool)@file_put_contents($dir . '/' . date('His') . '-' . bin2hex(random_bytes(3)) . '.eml',
        implode("\r\n", array_merge($m['headers'], ['To: <' . $m['to'] . '>', 'Subject: ' . $m['subject']])) .
        "\r\n\r\n" . $m['body']);
}

/* Build and send one mail, inside the per-address and site limits; the outcome is a word for the log. SMTP; on
   a refusal, one retry after about 60 s unless 60 s have already gone by, inside the 165 s execution limit.
   No other route: PHP's mail() would likely fail DMARC. Call after the answer has left. $reserved false for the
   mails a sign-up sends, which stop short of the site's cap (mail_allowed). */
function send_mail(string $kind, string $to, string $lang, string $link, ?string $second = null, bool $reserved = true): string {
    global $T0;
    if (!mail_allowed($to, $reserved)) return 'mail_limit';
    $m = mail_message($kind, $to, $lang, with_lang($link, $lang), $second === null ? null : with_lang($second, $lang));
    if ($m === null) return 'header_refused';
    if (cfg('transport') === 'file') return mail_to_file($m) ? 'sent_file' : 'file_failed';
    db_release();
    $deadline = $T0 + 140;
    $try = function () use ($m, $deadline): ?string {
        try { return smtp_send($m, $deadline); } catch (Throwable $e) { return 'exception'; }
    };
    $err = $try();
    if ($err === null) return 'sent';
    log_php('smtp ' . $err);
    if (microtime(true) - $T0 < 60) {
        sleep(60);
        $err = $try();
        if ($err === null) return 'sent_retry';
        log_php('smtp retry ' . $err);
    }
    return 'smtp_refused';
}
