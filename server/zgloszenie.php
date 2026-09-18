<?php
// Bug-report form handler for https://zwiedzajdolnyslask.pl/#zglos-blad.
// The page posts to /api/zgloszenie; vercel.json rewrites that to this file on the pdw.wroc.pl (LH.pl) hosting,
// the same server that hosts the pdw.wroc.pl mailboxes. Deployed at public_html/pdw/zgloszenie.php.

declare(strict_types=1);

const TO = 'zgloszenia@pdw.wroc.pl';
const FROM = 'pdw@pdw.wroc.pl'; // existing mailbox, so bounces are visible
const HOURLY_LIMIT = 30;

ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('Europe/Warsaw');

function reply(int $code, string $status): never
{
    http_response_code($code);
    echo json_encode(['status' => $status]);
    exit;
}

function text(string $key, int $max, bool $multiline = false): string
{
    $v = $_POST[$key] ?? '';
    if (!is_string($v) || !mb_check_encoding($v, 'UTF-8')) return '';
    $v = preg_replace('/[\x00-\x08\x0B-\x1F\x7F]/', '', str_replace("\r\n", "\n", $v));
    if (!$multiline) $v = preg_replace('/\s+/u', ' ', $v);
    return mb_substr(trim($v), 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') reply(405, 'method');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && $origin !== 'https://zwiedzajdolnyslask.pl') reply(403, 'origin');

// Bots: a filled honeypot gets a fake success; a form sent faster than a person can type is rejected.
if (text('website', 100) !== '') reply(200, 'ok');
if ((int)text('e', 10) < 3) reply(400, 'too_fast');

$systems = ['android' => 'Android', 'ios' => 'iOS', 'inne' => 'inny / nie wiem'];
$system = $systems[text('system', 20)] ?? null;
$device = text('device', 200);
$place = text('place', 200);
$desc = text('desc', 5000, true);
$email = text('email', 200);
$lang = text('lang', 5);
$lang = in_array($lang, ['pl', 'en', 'de'], true) ? $lang : 'pl';

if ($system === null || mb_strlen($desc) < 10) reply(400, 'invalid');
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) reply(400, 'email');

// Global cap so a scripted flood cannot get the hosting account's outgoing mail suspended.
$log = @fopen(sys_get_temp_dir() . '/zds-zgloszenia.log', 'c+');
if ($log && flock($log, LOCK_EX)) {
    $now = time();
    $recent = array_filter(
        array_map('intval', explode("\n", (string)stream_get_contents($log))),
        fn(int $t) => $t > $now - 3600
    );
    if (count($recent) >= HOURLY_LIMIT) reply(429, 'limit');
    $recent[] = $now;
    ftruncate($log, 0);
    rewind($log);
    fwrite($log, implode("\n", $recent));
    flock($log, LOCK_UN);
}

$ua = substr(preg_replace('/\s+/', ' ', (string)($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 300);
$subject = "Zgłoszenie błędu ($system): " . mb_substr(preg_replace('/\s+/u', ' ', $desc), 0, 60);
$body = implode("\n", [
    'Nowe zgłoszenie błędu z formularza na zwiedzajdolnyslask.pl',
    '',
    'System:      ' . $system,
    'Urządzenie:  ' . ($device !== '' ? $device : '—'),
    'Gdzie:       ' . ($place !== '' ? $place : '—'),
    'E-mail:      ' . ($email !== '' ? $email . '  (odpowiedz na tę wiadomość)' : 'nie podano'),
    'Język:       ' . $lang,
    '',
    'Opis:',
    $desc,
    '',
    '-- ',
    'Wysłano: ' . date('Y-m-d H:i'),
    'Przeglądarka zgłaszającego: ' . ($ua !== '' ? $ua : '—'),
]);

$headers = [
    'From' => 'Formularz zwiedzajdolnyslask.pl <' . FROM . '>',
    'MIME-Version' => '1.0',
    'Content-Type' => 'text/plain; charset=UTF-8',
    'Content-Transfer-Encoding' => 'quoted-printable',
];
if ($email !== '') $headers['Reply-To'] = $email;

$sent = mail(TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', quoted_printable_encode($body), $headers, '-f' . FROM);
reply($sent ? 200 : 502, $sent ? 'ok' : 'send');
