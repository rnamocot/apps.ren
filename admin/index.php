<?php
/**
 * Revenue admin for apps.renienamocot.com
 * - First visit: create a password (needs the setup key from private/config.php)
 * - Then: log in and set MRR / revenue per app. Saved to /data/revenue.json,
 *   which the public page reads.
 */
declare(strict_types=1);
date_default_timezone_set('Asia/Manila');

const ROOT        = __DIR__ . '/..';
const PRIV        = ROOT . '/private';
const AUTH_FILE   = PRIV . '/auth.php';
const TRIES_FILE  = PRIV . '/attempts.json';
const DATA_FILE   = ROOT . '/data/revenue.json';
const MAX_TRIES   = 5;
const LOCK_SECS   = 15 * 60;
const IDLE_SECS   = 60 * 60;
const MODES       = ['mrr' => 'Per month (MRR)', 'revenue' => 'Total revenue', 'hidden' => 'Hidden'];

$config = require PRIV . '/config.php';

/* ---------- security headers ---------- */
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store');

/* ---------- session ---------- */
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_name('rn_admin');
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();

if (!empty($_SESSION['auth']) && (time() - ($_SESSION['seen'] ?? 0)) > IDLE_SECS) {
    $_SESSION = [];
    session_regenerate_id(true);
}
if (!empty($_SESSION['auth'])) $_SESSION['seen'] = time();
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));

/* ---------- helpers ---------- */
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function csrf_ok(): bool {
    return isset($_POST['csrf']) && is_string($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf']);
}

function write_atomic(string $file, string $contents): bool {
    if (!is_dir(dirname($file))) @mkdir(dirname($file), 0755, true);
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $contents, LOCK_EX) === false) return false;
    return rename($tmp, $file);
}

function client_key(): string { return hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown'); }

function tries_load(): array {
    $d = is_file(TRIES_FILE) ? json_decode((string)file_get_contents(TRIES_FILE), true) : [];
    return is_array($d) ? $d : [];
}
function locked_for(): int {
    $t = tries_load()[client_key()] ?? null;
    return ($t && ($t['until'] ?? 0) > time()) ? (int)$t['until'] - time() : 0;
}
function fail_attempt(): void {
    $all = tries_load();
    $k = client_key();
    $t = $all[$k] ?? ['n' => 0, 'until' => 0];
    $t['n']++;
    if ($t['n'] >= MAX_TRIES) { $t['until'] = time() + LOCK_SECS; $t['n'] = 0; }
    $all[$k] = $t;
    foreach ($all as $kk => $v) if (($v['until'] ?? 0) < time() - 86400 && ($v['n'] ?? 0) === 0) unset($all[$kk]);
    write_atomic(TRIES_FILE, json_encode($all));
}
function clear_attempts(): void {
    $all = tries_load();
    unset($all[client_key()]);
    write_atomic(TRIES_FILE, json_encode($all));
}

function auth_hash(): ?string {
    if (!is_file(AUTH_FILE)) return null;
    $a = require AUTH_FILE;
    return is_array($a) && !empty($a['hash']) ? $a['hash'] : null;
}
function save_hash(string $password): bool {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    return write_atomic(AUTH_FILE, "<?php\nreturn " . var_export(['hash' => $hash], true) . ";\n");
}

function load_data(): array {
    $d = is_file(DATA_FILE) ? json_decode((string)file_get_contents(DATA_FILE), true) : null;
    if (!is_array($d)) $d = [];
    $d['apps'] = is_array($d['apps'] ?? null) ? $d['apps'] : [];
    return $d;
}

/* ---------- actions ---------- */
$flash = '';
$error = '';
$hash = auth_hash();
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($action === 'setup' && $hash === null) {
        if ($wait = locked_for()) {
            $error = 'Too many attempts. Try again in ' . ceil($wait / 60) . ' min.';
        } elseif (!hash_equals((string)$config['setup_key'], (string)($_POST['setup_key'] ?? ''))) {
            fail_attempt();
            $error = 'That setup key is not correct.';
        } else {
            $p1 = (string)($_POST['password'] ?? '');
            $p2 = (string)($_POST['password2'] ?? '');
            if (strlen($p1) < 10) $error = 'Use at least 10 characters.';
            elseif ($p1 !== $p2) $error = 'The passwords do not match.';
            elseif (!save_hash($p1)) $error = 'Could not save. Check that the private/ folder is writable.';
            else {
                clear_attempts();
                session_regenerate_id(true);
                $_SESSION['auth'] = true;
                $_SESSION['seen'] = time();
                $hash = auth_hash();
                $flash = 'Password created. You are logged in.';
            }
        }
    } elseif ($action === 'login' && $hash !== null) {
        if ($wait = locked_for()) {
            $error = 'Too many attempts. Try again in ' . ceil($wait / 60) . ' min.';
        } elseif (password_verify((string)($_POST['password'] ?? ''), $hash)) {
            clear_attempts();
            session_regenerate_id(true);
            $_SESSION['auth'] = true;
            $_SESSION['seen'] = time();
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) save_hash((string)$_POST['password']);
            header('Location: ./', true, 303);
            exit;
        } else {
            fail_attempt();
            usleep(400000);
            $error = 'Wrong password.';
        }
    } elseif ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        header('Location: ./', true, 303);
        exit;
    } elseif (!empty($_SESSION['auth']) && $action === 'save') {
        $in = $_POST['apps'] ?? [];
        $out = [];
        foreach ($config['apps'] as $slug => $name) {
            $row = is_array($in[$slug] ?? null) ? $in[$slug] : [];
            $amount = str_replace([',', ' '], '', (string)($row['amount'] ?? '0'));
            $amount = is_numeric($amount) ? max(0, min(1e10, (float)$amount)) : 0;
            $cur = array_key_exists($row['currency'] ?? '', $config['currencies']) ? $row['currency'] : 'PHP';
            $mode = array_key_exists($row['mode'] ?? '', MODES) ? $row['mode'] : 'hidden';
            $out[$slug] = ['amount' => round($amount, 2), 'currency' => $cur, 'mode' => $mode];
        }
        $data = ['updated' => date('c'), 'apps' => $out];
        if (write_atomic(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $_SESSION['flash'] = 'Saved. The site shows the new numbers now.';
            header('Location: ./', true, 303);
            exit;
        }
        $error = 'Could not save. Check that the data/ folder is writable.';
    } elseif (!empty($_SESSION['auth']) && $action === 'password') {
        $cur = (string)($_POST['current'] ?? '');
        $p1 = (string)($_POST['password'] ?? '');
        $p2 = (string)($_POST['password2'] ?? '');
        if (!password_verify($cur, (string)$hash)) $error = 'Current password is wrong.';
        elseif (strlen($p1) < 10) $error = 'Use at least 10 characters.';
        elseif ($p1 !== $p2) $error = 'The new passwords do not match.';
        elseif (!save_hash($p1)) $error = 'Could not save the new password.';
        else { session_regenerate_id(true); $flash = 'Password changed.'; }
    }
}

if (!empty($_SESSION['flash'])) { $flash = $_SESSION['flash']; unset($_SESSION['flash']); }
$authed = !empty($_SESSION['auth']) && $hash !== null;
$data = load_data();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Admin · Renie Namocot</title>
  <link rel="icon" href="../favicon.ico" sizes="48x48">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../styles.css?v=20261126b">
  <style>
    body { background: var(--background); }
    .admin { width: 100%; max-width: 760px; margin: 0 auto; padding: 48px 20px 80px; }
    .admin__top { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 28px; }
    .admin__brand { display: flex; align-items: center; gap: 10px; font-weight: 600; letter-spacing: -0.02em; }
    .admin__brand img { width: 32px; height: 32px; border-radius: 50%; }
    .panel { padding: 24px; border-radius: 16px; background: var(--card); box-shadow: var(--surface-shadow); }
    .panel + .panel { margin-top: 16px; }
    .panel h1, .panel h2 { font-size: 18px; font-weight: 600; letter-spacing: -0.02em; }
    .panel > p { margin-top: 6px; font-size: 14px; color: var(--muted-foreground); }
    .narrow { max-width: 400px; margin: 10vh auto 0; }
    label.lbl { display: block; margin: 16px 0 6px; font-size: 13px; font-weight: 500; }
    .in, .sel {
      width: 100%; height: 40px; padding: 0 12px; border-radius: 10px;
      border: 1px solid var(--border); background: var(--background); color: var(--foreground);
      font: inherit; font-size: 15px; outline: none;
    }
    .in:focus, .sel:focus { border-color: var(--ring); box-shadow: 0 0 0 3px color-mix(in oklab, var(--ring) 25%, transparent); }
    .btn-full { width: 100%; margin-top: 20px; height: 40px; border: 0; cursor: pointer; }
    button.btn { border: 0; cursor: pointer; font-family: inherit; }
    .ghost { background: transparent; color: var(--muted-foreground); border: 1px solid var(--border); height: 36px; padding: 0 14px; border-radius: 8px; font: inherit; font-size: 14px; cursor: pointer; }
    .ghost:hover { color: var(--foreground); background: var(--muted); }
    .note { margin-top: 16px; padding: 10px 12px; border-radius: 10px; font-size: 14px; }
    .note--ok { background: #dcfce7; color: #14532d; }
    .note--err { background: #fee2e2; color: #7f1d1d; }
    @media (prefers-color-scheme: dark) { .note--ok { background: #052e16; color: #bbf7d0; } .note--err { background: #450a0a; color: #fecaca; } }
    .rows { margin-top: 20px; display: grid; gap: 10px; }
    .row { display: grid; grid-template-columns: 1.3fr 1fr 90px 1.1fr; gap: 10px; align-items: center; padding: 12px; border-radius: 12px; border: 1px solid var(--border); }
    .row__name { font-weight: 500; font-size: 15px; }
    .row__name small { display: block; font-weight: 400; font-size: 12px; color: var(--muted-foreground); }
    .rows__head { display: grid; grid-template-columns: 1.3fr 1fr 90px 1.1fr; gap: 10px; padding: 0 13px; font-size: 12px; color: var(--muted-foreground); }
    .actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 20px; flex-wrap: wrap; }
    .meta { font-size: 13px; color: var(--muted-foreground); }
    details summary { cursor: pointer; font-weight: 600; font-size: 15px; }
    @media (max-width: 600px) {
      .rows__head { display: none; }
      .row { grid-template-columns: 1fr 90px; }
      .row__name { grid-column: 1 / -1; }
      .row .mode { grid-column: 1 / -1; }
    }
  </style>
</head>
<body>
<main class="admin">

<?php if ($hash === null): ?>
  <section class="panel narrow">
    <h1>Set up your admin</h1>
    <p>Enter the setup key from <code>private/config.php</code> and choose a password.</p>
    <?php if ($error): ?><div class="note note--err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="setup">
      <label class="lbl" for="k">Setup key</label>
      <input class="in" id="k" name="setup_key" required>
      <label class="lbl" for="p1">New password</label>
      <input class="in" id="p1" type="password" name="password" minlength="10" autocomplete="new-password" required>
      <label class="lbl" for="p2">Repeat password</label>
      <input class="in" id="p2" type="password" name="password2" minlength="10" autocomplete="new-password" required>
      <button class="btn btn-full" type="submit">Create password</button>
    </form>
  </section>

<?php elseif (!$authed): ?>
  <section class="panel narrow">
    <div class="admin__brand"><img src="../avatar.png" alt=""> Admin</div>
    <p style="margin-top:12px">Log in to update your app revenue.</p>
    <?php if ($error): ?><div class="note note--err" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="login">
      <label class="lbl" for="pw">Password</label>
      <input class="in" id="pw" type="password" name="password" autocomplete="current-password" required autofocus>
      <button class="btn btn-full" type="submit">Log in</button>
    </form>
  </section>

<?php else: ?>
  <div class="admin__top">
    <div class="admin__brand"><img src="../avatar.png" alt=""> Revenue admin</div>
    <div style="display:flex;gap:8px">
      <a class="ghost" style="display:inline-flex;align-items:center" href="../" target="_blank" rel="noopener">View site ↗</a>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="logout">
        <button class="ghost" type="submit">Log out</button>
      </form>
    </div>
  </div>

  <section class="panel">
    <h1>MRR &amp; revenue</h1>
    <p>Set an amount for each app. "Per month" shows as ₱12,000/mo, "Total revenue" shows as ₱12,000 revenue, "Hidden" shows nothing.</p>
    <?php if ($flash): ?><div class="note note--ok" role="status"><?= e($flash) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="note note--err" role="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
      <input type="hidden" name="action" value="save">
      <div class="rows">
        <div class="rows__head"><span>App</span><span>Amount</span><span>Currency</span><span>Show as</span></div>
        <?php foreach ($config['apps'] as $slug => $name):
          $r = $data['apps'][$slug] ?? ['amount' => 0, 'currency' => 'PHP', 'mode' => 'hidden']; ?>
          <div class="row">
            <div class="row__name"><?= e($name) ?><small><?= e($slug) ?></small></div>
            <input class="in" name="apps[<?= e($slug) ?>][amount]" inputmode="decimal" aria-label="<?= e($name) ?> amount"
                   value="<?= e(rtrim(rtrim(number_format((float)$r['amount'], 2, '.', ''), '0'), '.')) ?>">
            <select class="sel" name="apps[<?= e($slug) ?>][currency]" aria-label="<?= e($name) ?> currency">
              <?php foreach ($config['currencies'] as $code => $sym): ?>
                <option value="<?= e($code) ?>" <?= $r['currency'] === $code ? 'selected' : '' ?>><?= e($sym . ' ' . $code) ?></option>
              <?php endforeach; ?>
            </select>
            <select class="sel mode" name="apps[<?= e($slug) ?>][mode]" aria-label="<?= e($name) ?> show as">
              <?php foreach (MODES as $m => $label): ?>
                <option value="<?= e($m) ?>" <?= $r['mode'] === $m ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="actions">
        <span class="meta"><?= !empty($data['updated']) ? 'Last saved ' . e(date('M j, Y g:i A', strtotime($data['updated']))) : 'Not saved yet' ?></span>
        <button class="btn" type="submit">Save changes</button>
      </div>
    </form>
  </section>

  <section class="panel">
    <details>
      <summary>Change password</summary>
      <form method="post" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="action" value="password">
        <label class="lbl" for="c0">Current password</label>
        <input class="in" id="c0" type="password" name="current" autocomplete="current-password" required>
        <label class="lbl" for="c1">New password</label>
        <input class="in" id="c1" type="password" name="password" minlength="10" autocomplete="new-password" required>
        <label class="lbl" for="c2">Repeat new password</label>
        <input class="in" id="c2" type="password" name="password2" minlength="10" autocomplete="new-password" required>
        <button class="btn btn-full" type="submit">Change password</button>
      </form>
    </details>
  </section>
<?php endif; ?>

</main>
</body>
</html>
