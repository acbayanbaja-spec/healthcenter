<?php
require_once 'db.php';
$error = '';
if (!empty($_SESSION['user']) && !empty($_SESSION['uid'])) { header('Location: ' . home_for(role())); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$_POST['username'] ?? '']);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        if (($_POST['password'] ?? '') === 'password') {   // the well-known default: make them pick their own
            try { $pdo->prepare('UPDATE users SET must_change_password=1 WHERE user_id=?')->execute([$u['user_id']]); } catch (PDOException $e) {}
        }
        $_SESSION['user'] = $u['full_name'];
        $_SESSION['uid'] = (int)$u['user_id'];
        $r = $u['role'] ?? 'admin';
        $_SESSION['role'] = in_array($r, ['admin', 'staff', 'doctor', 'patient'], true) ? $r : 'staff';
        $_SESSION['doctor_id'] = (int)($u['doctor_id'] ?? 0);
        $_SESSION['patient_id'] = (int)($u['patient_id'] ?? 0);
        log_event($pdo, 'Login', 'users', $u['user_id'], 'Signed in as ' . $_SESSION['role']);
        header('Location: ' . home_for($_SESSION['role'])); exit;
    }
    $error = 'Invalid username or password.';
}
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login</title><link rel="stylesheet" href="style.css"><script src="theme.js"></script><link rel="icon" type="image/svg+xml" href="logo.svg"></head><body>
<div class="auth"><div class="auth-art"><div class="brand"><?= logo_html(72) ?><span>Health Center</span></div><h1>Every visit, on the record.</h1><p>Manage patients, doctors and appointments in one calm place.</p><svg class="ecg" viewBox="0 0 600 110" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60 H120 L140 60 L158 20 L182 100 L204 8 L224 60 H330 L346 60 L362 34 L380 84 L396 60 H600"/></svg></div><div class="auth-form"><div>
  <h2>Log in</h2><p style="color:var(--muted)">Welcome back.</p>
  <?php if (isset($_GET['info'])): ?><div class="msg ok">Patient accounts are created by the health center staff. Please register at the front desk.</div><?php endif; ?>
  <?php if ($error): ?><div class="msg err"><?= h($error) ?></div><?php endif; ?>
  <form method="post">
    <p><input name="username" placeholder="Username" required></p>
    <p><input name="password" type="password" placeholder="Password" required></p>
    <button>Log in</button>
  </form>
  <p><small>New patient? Please ask the front desk to register you. · <a href="terms.php">User Agreement</a></small></p><p><small style="color:var(--muted)">Demo account: admin / password</small></p>
</div></div></div></body></html>
