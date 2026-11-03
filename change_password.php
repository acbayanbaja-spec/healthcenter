<?php
require_once 'db.php'; require_login();
if (empty($_SESSION['must_change'])) { header('Location: profile.php'); exit; }
$uid = (int)$_SESSION['uid']; $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = $pdo->prepare('SELECT password FROM users WHERE user_id=?'); $s->execute([$uid]); $hash = $s->fetchColumn();
    $new = $_POST['new_password'] ?? '';
    if (!$hash || !password_verify($_POST['current_password'] ?? '', $hash)) $err = 'The temporary password is not correct.';
    elseif (strlen($new) < 6) $err = 'The new password must be at least 6 characters.';
    elseif ($new !== ($_POST['confirm_password'] ?? '')) $err = 'The new passwords do not match.';
    elseif (password_verify($new, $hash)) $err = 'Choose a password different from the temporary one.';
    else {
        $pdo->prepare('UPDATE users SET password=?, must_change_password=0 WHERE user_id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
        $_SESSION['must_change'] = 0;
        log_event($pdo, 'Updated', 'users', $uid, 'Password changed at first login');
        session_regenerate_id(true);
        header('Location: ' . home_for(role())); exit;
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Choose a new password</title><link rel="stylesheet" href="style.css"><script src="theme.js"></script><link rel="icon" type="image/svg+xml" href="logo.svg"></head><body>
<div class="legal" style="max-width:440px"><div class="card">
  <div style="margin-bottom:14px"><?= logo_html(56) ?></div>
  <h2>Choose a new password</h2>
  <p class="note">Hi <?= h(explode(' ', $_SESSION['user'])[0]) ?>, you are using a temporary password. Please set your own before you continue.</p>
  <?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" autocomplete="off">
    <p><input type="password" name="current_password" placeholder="Temporary password" required autocomplete="current-password"></p>
    <p><input type="password" name="new_password" placeholder="New password (min 6)" required autocomplete="new-password"></p>
    <p><input type="password" name="confirm_password" placeholder="Confirm new password" required autocomplete="new-password"></p>
    <button>Save and continue</button> <a class="btn gray" href="logout.php">Log out</a>
  </form>
</div></div></body></html>
