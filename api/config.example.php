<?php
/* Nokime Jobs accounts: the example of the real configuration. The real file is <home>/jobs-private/config.php,
   beside the site folder and never in this repository (it is public): Gabriel copies this file there over SFTP,
   types the two passwords into it himself, and sets it to mode 0600. Next to it goes <home>/jobs-private/hmac.key,
   exactly 32 random bytes made on the Mac (head -c 32 /dev/urandom > hmac.key), mode 0600. Without both files
   every /api/auth/ endpoint answers 503. Requested over HTTP, this example answers 404. */
if (get_included_files()[0] === __FILE__) { http_response_code(404); exit; }
return [
    // The database created in the OVH panel (Web Cloud > Hébergements > nokime.fr > Bases de données).
    'db' => [
        'dsn'  => 'mysql:host=DATABASE-HOST.mysql.db;port=3306;dbname=DATABASE-NAME;charset=utf8mb4',
        'user' => 'DATABASE-USER',
        'pass' => 'DATABASE-PASSWORD',
    ],
    // The mailbox comptes@nokime.fr, through OVH's authenticated SMTP. Never PHP's mail().
    'transport' => 'smtp',
    'smtp' => ['host' => 'smtp.mail.ovh.net', 'port' => 465, 'user' => 'comptes@nokime.fr', 'pass' => 'MAILBOX-PASSWORD'],
    'from' => 'comptes@nokime.fr', 'from_name' => 'Nokime Jobs', 'reply_to' => 'contact@nokime.fr',
    // Mails per hour for the whole site, under the mailbox's own SMTP quota (about 200 an hour, to check with OVH).
    // Sign-ups may use all but 50 of them; the rest stays for reset links and the other mails.
    'mail_site_cap' => 150,
    // false if the dark test shows that PHP sees OVH's balancer in REMOTE_ADDR instead of the visitor.
    'per_ip_limits' => true,
    // true only for the dark test: /api/auth/probe.php answers and log lines are marked. Then false again.
    // On OVH it changes no limit; the limits x10 apply only on the local rig, which also sets probe_origin.
    'probe_mode' => false,
    // The local rig's address only (accepted as Origin, used in links, limits x10). Always null on OVH.
    'probe_origin' => null,
];
