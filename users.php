<?php require_once 'db.php'; require_role(['admin']);
$msg = ''; $err = '';
$hasRole = (bool)$pdo->query("SHOW COLUMNS FROM users LIKE 'role'")->fetch();
$roles = ['admin', 'staff', 'doctor', 'patient'];
$meId = (int)$_SESSION['uid'];
function link_check($pdo, $role, $did, $pid) {
    if ($role === 'doctor') { $s = $pdo->prepare('SELECT 1 FROM doctors WHERE doctor_id=?'); $s->execute([$did]); return $s->fetch() ? null : 'Choose the doctor record this account belongs to.'; }
    if ($role === 'patient') { $s = $pdo->prepare('SELECT 1 FROM patients WHERE patient_id=?'); $s->execute([$pid]); return $s->fetch() ? null : 'Choose the patient record this account belongs to.'; }
    return null;
}
if ($hasRole && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $do = $_POST['do'] ?? '';
        $uid = (int)($_POST['user_id'] ?? 0);
        $role = in_array($_POST['role'] ?? '', $roles, true) ? $_POST['role'] : 'staff';
        $did = $role === 'doctor' ? (int)($_POST['doctor_id'] ?? 0) : null;
        $pid = $role === 'patient' ? (int)($_POST['patient_id'] ?? 0) : null;
        $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
        $target = null;
        if ($uid) { $t = $pdo->prepare('SELECT * FROM users WHERE user_id=?'); $t->execute([$uid]); $target = $t->fetch(); }
        if ($do === 'create') {
            $name = trim($_POST['full_name'] ?? ''); $user = trim($_POST['username'] ?? ''); $pass = $_POST['password'] ?? '';
            $c = $pdo->prepare('SELECT 1 FROM users WHERE username=?'); $c->execute([$user]);
            if ($name === '' || $user === '') $err = 'Name and username are required.';
            elseif (strlen($pass) < 6) $err = 'The password must be at least 6 characters.';
            elseif ($c->fetch()) $err = 'That username is already taken.';
            elseif ($e = link_check($pdo, $role, $did, $pid)) $err = $e;
            else {
                $pdo->prepare('INSERT INTO users (username,password,full_name,role,doctor_id,patient_id) VALUES (?,?,?,?,?,?)')->execute([$user, password_hash($pass, PASSWORD_DEFAULT), $name, $role, $did, $pid]);
                $newId = (int)$pdo->lastInsertId();
                try { $pdo->prepare('UPDATE users SET must_change_password=1 WHERE user_id=?')->execute([$newId]); } catch (PDOException $e) {}
                log_event($pdo, 'Created', 'users', $newId, "Account created: $user ($role)");
                $msg = "Account created for $name. Give them the username and temporary password. They will choose their own password at first login.";
            }
        } elseif ($do === 'update' && $target) {
            if ($target['role'] === 'admin' && $role !== 'admin' && $admins <= 1) $err = 'There must be at least one admin.';
            elseif ($e = link_check($pdo, $role, $did, $pid)) $err = $e;
            else {
                $pdo->prepare('UPDATE users SET role=?, doctor_id=?, patient_id=? WHERE user_id=?')->execute([$role, $did, $pid, $uid]);
                log_event($pdo, 'Updated', 'users', $uid, "Role of {$target['username']}: {$target['role']} → $role");
                $msg = 'Account updated. The change applies the next time they open a page.';
            }
        } elseif ($do === 'password' && $target) {
            if (strlen($_POST['new_password'] ?? '') < 6) $err = 'The new password must be at least 6 characters.';
            else {
                $pdo->prepare('UPDATE users SET password=? WHERE user_id=?')->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $uid]);
                if ($uid !== $meId) { try { $pdo->prepare('UPDATE users SET must_change_password=1 WHERE user_id=?')->execute([$uid]); } catch (PDOException $e) {} }
                log_event($pdo, 'Updated', 'users', $uid, "Password reset for {$target['username']}");
                $msg = 'Password reset.' . ($uid !== $meId ? ' They will choose a new password at next login.' : '');
            }
        } elseif ($do === 'delete' && $target) {
            if ($uid === $meId) $err = 'You cannot delete your own account.';
            elseif ($target['role'] === 'admin' && $admins <= 1) $err = 'There must be at least one admin.';
            else {
                remove_image($pdo, $uid, 'profile'); remove_image($pdo, $uid, 'cover');
                $pdo->prepare('DELETE FROM users WHERE user_id=?')->execute([$uid]);
                log_event($pdo, 'Deleted', 'users', $uid, "Account deleted: {$target['username']} ({$target['role']})");
                $msg = 'Account deleted.';
            }
        }
    } catch (PDOException $e) { $err = 'Database error.'; }
}
include 'header.php';
$doctors = $pdo->query('SELECT doctor_id, full_name FROM doctors ORDER BY full_name')->fetchAll();
$patients = $pdo->query('SELECT patient_id, full_name FROM patients ORDER BY full_name')->fetchAll();
$users = $pdo->query('SELECT * FROM users ORDER BY full_name')->fetchAll();
function opts($rows, $key, $sel) { $o = '<option value="">—</option>'; foreach ($rows as $r) $o .= '<option value="' . $r[$key] . '"' . ((int)$sel === (int)$r[$key] ? ' selected' : '') . '>' . h($r['full_name']) . '</option>'; return $o; }
?>
<h2>Users</h2>
<p class="note">Admins manage every account. Walk-in patient accounts are created by staff in Register. Staff and doctor accounts are created here.</p>
<?php if (!$hasRole): ?><div class="msg err">Roles are not set up yet. Run <b>migrate_roles.sql</b> in phpMyAdmin, then reload this page.</div><?php endif; ?>
<?php if ($msg): ?><div class="msg ok"><?= h($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="msg err"><?= h($err) ?></div><?php endif; ?>
<?php if ($hasRole): ?>
<div class="card"><h3>Create an account</h3>
<form method="post" class="grid" autocomplete="off">
  <input type="hidden" name="do" value="create">
  <input name="full_name" placeholder="Full name" required>
  <input name="username" placeholder="Username" required>
  <input name="password" type="text" placeholder="Temporary password (min 6)" required>
  <select name="role" aria-label="Role"><?php foreach ($roles as $r): ?><option value="<?= $r ?>"><?= h(role_label($r)) ?></option><?php endforeach; ?></select>
  <select name="doctor_id" aria-label="Doctor record"><?= opts($doctors, 'doctor_id', 0) ?></select>
  <select name="patient_id" aria-label="Patient record"><?= opts($patients, 'patient_id', 0) ?></select>
  <div><button>Create account</button></div>
</form>
<p class="note">For a Doctor role pick the doctor record. For a Patient role pick the patient record. Staff and Admin need neither.</p></div>
<div class="card"><h3>All accounts (<?= count($users) ?>)</h3>
<table><tr><th>Account</th><th>Role and linked record</th><th>Agreement</th><th>Password</th><th></th></tr>
<?php foreach ($users as $u): ?>
<tr><td><?= avatar_html($u) ?><?= h($u['full_name']) ?><br><small class="note">@<?= h($u['username']) ?></small></td>
<td><form method="post" style="display:flex;gap:6px;flex-wrap:wrap"><input type="hidden" name="do" value="update"><input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
  <select name="role" style="width:auto"><?php foreach ($roles as $r): ?><option value="<?= $r ?>" <?= ($u['role'] ?? '') === $r ? 'selected' : '' ?>><?= h(role_label($r)) ?></option><?php endforeach; ?></select>
  <select name="doctor_id" style="width:auto" aria-label="Doctor record"><?= opts($doctors, 'doctor_id', $u['doctor_id'] ?? 0) ?></select>
  <select name="patient_id" style="width:auto" aria-label="Patient record"><?= opts($patients, 'patient_id', $u['patient_id'] ?? 0) ?></select>
  <button>Save</button></form></td>
<td><?= !empty($u['agreed_at']) ? h(date('j M Y', strtotime($u['agreed_at']))) : '—' ?></td>
<td><form method="post" style="display:flex;gap:6px"><input type="hidden" name="do" value="password"><input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
  <input name="new_password" placeholder="New password" style="width:130px" autocomplete="off"><button class="gray">Reset</button></form></td>
<td><?php if ((int)$u['user_id'] !== $meId): ?><form method="post" onsubmit="return confirm('Delete this account?')"><input type="hidden" name="do" value="delete"><input type="hidden" name="user_id" value="<?= $u['user_id'] ?>"><button class="red">Delete</button></form><?php else: ?><small class="note">You</small><?php endif; ?></td></tr>
<?php endforeach; ?></table></div>
<?php endif; ?>
<?php include 'footer.php'; ?>
