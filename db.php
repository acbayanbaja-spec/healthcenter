<?php
require_once __DIR__ . '/config_sms.php';
session_start();
try {
    $pdo = new PDO('mysql:host=localhost;dbname=healthcenter_db;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("SET time_zone = '" . date('P') . "'");   // keep MySQL's NOW() in step with PHP's clock
} catch (PDOException $e) {
    http_response_code(500);
    exit('<!DOCTYPE html><meta charset="utf-8"><body style="font-family:sans-serif;max-width:560px;margin:60px auto"><h2>Cannot connect to the database</h2><p>Start <b>MySQL</b> in the XAMPP Control Panel, then import <b>schema.sql</b> in phpMyAdmin. Check the user name and password in <b>db.php</b>.</p><p style="color:#666">Details: ' . htmlspecialchars($e->getMessage()) . '</p>');
}
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function logo_html($size = 48) {
    $s = (int)$size;
    return '<svg class="lg" width="' . $s . '" height="' . $s . '" viewBox="0 0 64 64" role="img" aria-label="Health Center logo">'
        . '<circle class="lg-ring" cx="32" cy="32" r="30"/>'
        . '<path class="lg-tile" d="M20 0H44a20 20 0 0 1 20 20V44a20 20 0 0 1-20 20H8a8 8 0 0 1-8-8V20A20 20 0 0 1 20 0z"/>'
        . '<path class="lg-cross" d="M26 12h12v14h14v12H38v14H26V38H12V26h14z"/>'
        . '<path class="lg-line" pathLength="100" d="M5 34H21L27 21L35 47L40 30L43 34H59"/></svg>';
}
function require_login() {
    global $pdo;
    if (empty($_SESSION['user']) || empty($_SESSION['uid'])) { header('Location: login.php'); exit; }
    $s = $pdo->prepare('SELECT * FROM users WHERE user_id=?'); $s->execute([$_SESSION['uid']]); $u = $s->fetch();
    if (!$u) { session_destroy(); header('Location: login.php'); exit; }
    $r = $u['role'] ?? 'admin';   // no role column yet: behave like before
    $_SESSION['role'] = in_array($r, ['admin', 'staff', 'doctor', 'patient'], true) ? $r : 'staff';
    $_SESSION['doctor_id'] = (int)($u['doctor_id'] ?? 0);
    $_SESSION['patient_id'] = (int)($u['patient_id'] ?? 0);
    $_SESSION['must_change'] = (int)($u['must_change_password'] ?? 0);
    if ($_SESSION['must_change'] && !in_array(basename($_SERVER['SCRIPT_NAME']), ['change_password.php', 'logout.php'], true)) { header('Location: change_password.php'); exit; }
}
function role() { return $_SESSION['role'] ?? 'admin'; }
function role_label($r) { return ['admin' => 'Admin', 'staff' => 'Staff', 'doctor' => 'Doctor', 'patient' => 'Patient'][$r] ?? 'Staff'; }
function home_for($r) { return ['patient' => 'portal.php', 'doctor' => 'doctor.php'][$r] ?? 'index.php'; }
function require_role($roles) {
    require_login();
    if (!in_array(role(), (array)$roles, true)) { header('Location: ' . home_for(role()) . '?denied=1'); exit; }
}

// ---- History (audit trail) ----
function log_event($pdo, $action, $table, $id, $details = '', $user = null) {
    $user = $user ?? ($_SESSION['user'] ?? 'System');
    try {
        $pdo->prepare('INSERT INTO history_log (user_name,action,table_name,record_id,details) VALUES (?,?,?,?,?)')
            ->execute([$user, $action, $table, $id, $details]);
    } catch (PDOException $e) { /* history table missing: keep the app working */ }
}
function snap($row, $skip = 1) {
    $o = []; $i = 0;
    foreach ($row as $k => $v) { if ($i++ < $skip) continue; $o[] = "$k: $v"; }
    return implode('; ', $o);
}
function changes($old, $new) {
    if (!$old) return snap($new, 0);
    $o = [];
    foreach ($new as $k => $v) if ((string)$old[$k] !== (string)$v) $o[] = "$k: " . ($old[$k] === '' ? '(empty)' : $old[$k]) . " → " . ($v === '' ? '(empty)' : $v);
    return $o ? implode('; ', $o) : 'No fields changed';
}
function history_rows($pdo, $f, $limit = 500) {
    $w = []; $p = [];
    if (!empty($f['table']))  { $w[] = 'table_name=?'; $p[] = $f['table']; }
    if (!empty($f['action'])) { $w[] = 'action=?';     $p[] = $f['action']; }
    if (!empty($f['id']))     { $w[] = 'record_id=?';  $p[] = (int)$f['id']; }
    if (!empty($f['from']))   { $w[] = 'logged_at>=?'; $p[] = $f['from'] . ' 00:00:00'; }
    if (!empty($f['to']))     { $w[] = 'logged_at<=?'; $p[] = $f['to'] . ' 23:59:59'; }
    if (!empty($f['q']))      { $w[] = '(details LIKE ? OR user_name LIKE ?)'; $p[] = '%' . $f['q'] . '%'; $p[] = '%' . $f['q'] . '%'; }
    $sql = 'SELECT * FROM history_log' . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY logged_at DESC, log_id DESC LIMIT ' . (int)$limit;
    $s = $pdo->prepare($sql); $s->execute($p);
    return $s->fetchAll();
}

// ---- Profile & cover photos ----
function photo_url($name) { return 'uploads/' . rawurlencode($name); }
function avatar_html($u, $cls = '') {
    $c = 'av' . ($cls ? " $cls" : '');
    if (!empty($u['profile_photo'])) return '<img class="' . $c . '" src="' . photo_url($u['profile_photo']) . '" alt="">';
    return '<span class="' . $c . '">' . h(mb_strtoupper(mb_substr($u['full_name'], 0, 1))) . '</span>';
}
// $kind: 'profile' or 'cover'. Returns [true|false|null, message]; null = nothing uploaded.
function save_image($pdo, $uid, $kind, $file, $max) {
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return [null, ''];
    if ($file['error'] !== UPLOAD_ERR_OK) return [false, 'The upload failed. Try a smaller image.'];
    if ($file['size'] > $max) return [false, 'The image is larger than ' . ($max / 1048576) . ' MB.'];
    $info = @getimagesize($file['tmp_name']);
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($types[$info[2]])) return [false, 'Use a JPG, PNG, or WebP image.'];
    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = "u{$uid}_{$kind}_" . bin2hex(random_bytes(6)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name")) return [false, 'The image could not be saved.'];
    $col = $kind === 'cover' ? 'cover_photo' : 'profile_photo';
    $old = $pdo->prepare("SELECT $col FROM users WHERE user_id=?"); $old->execute([$uid]); $old = $old->fetchColumn();
    $pdo->prepare("UPDATE users SET $col=? WHERE user_id=?")->execute([$name, $uid]);
    if ($old && is_file("$dir/$old")) unlink("$dir/$old");
    return [true, $name];
}
function remove_image($pdo, $uid, $kind) {
    $col = $kind === 'cover' ? 'cover_photo' : 'profile_photo';
    $old = $pdo->prepare("SELECT $col FROM users WHERE user_id=?"); $old->execute([$uid]); $old = $old->fetchColumn();
    $pdo->prepare("UPDATE users SET $col=NULL WHERE user_id=?")->execute([$uid]);
    if ($old && is_file(__DIR__ . "/uploads/$old")) unlink(__DIR__ . "/uploads/$old");
}

require_once __DIR__ . '/sms.php';
require_once __DIR__ . '/appts.php';
