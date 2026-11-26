<?php
/**
 * Live visitors ("N here right now").
 * Each open page POSTs its random id every ~20s. We keep ids seen in the
 * last TTL seconds in private/presence.json (blocked from the web) and
 * return how many there are. No cookies, no IPs stored in plain text.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
header('X-Content-Type-Options: nosniff');

const STORE      = __DIR__ . '/../private/presence.json';
const TTL        = 45;     // seconds a visitor counts as "here"
const PER_IP_MAX = 5;      // max ids one IP can register (stops inflating the count)
const MAX_IDS    = 2000;   // hard cap on stored ids

$id    = (string)($_POST['id'] ?? '');
$leave = ($_POST['leave'] ?? '') === '1';
$valid = $_SERVER['REQUEST_METHOD'] === 'POST' && preg_match('/^[a-f0-9]{32}$/', $id) === 1;
$ip    = substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|presence'), 0, 16);

if (!is_dir(dirname(STORE))) @mkdir(dirname(STORE), 0755, true);
$fp = @fopen(STORE, 'c+');
if (!$fp) { http_response_code(500); echo '{"now":null}'; exit; }

flock($fp, LOCK_EX);
$data = json_decode((string)stream_get_contents($fp), true);
if (!is_array($data)) $data = [];

$now = time();
foreach ($data as $k => $v) {
    if (!is_array($v) || ($v['t'] ?? 0) < $now - TTL) unset($data[$k]);
}

if ($valid) {
    if ($leave) {
        if (($data[$id]['ip'] ?? null) === $ip) unset($data[$id]);
    } elseif (isset($data[$id])) {
        $data[$id]['t'] = $now;
    } else {
        $fromIp = count(array_filter($data, fn($v) => $v['ip'] === $ip));
        if ($fromIp < PER_IP_MAX && count($data) < MAX_IDS) $data[$id] = ['t' => $now, 'ip' => $ip];
    }
}

ftruncate($fp, 0);
rewind($fp);
fwrite($fp, json_encode($data));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['now' => count($data)]);
